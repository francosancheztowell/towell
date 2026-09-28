<?php

declare(strict_types=1);

namespace Tests\Unit\Telegram;

use App\Services\Telegram\TelegramEnvio;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * PERF-13: un envío a N chats sale en paralelo y con timeouts cortos.
 */
class TelegramEnvioTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $opciones = [];

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.telegram.bot_token', 'TOKEN');
    }

    public function test_el_mensaje_llega_a_todos_los_chats_con_el_mismo_texto(): void
    {
        $this->fakeTelegram();

        $resultados = app(TelegramEnvio::class)->mensaje(['111', '222', '333', '222'], 'Hola', ['parse_mode' => 'Markdown']);

        $this->assertSame(3, TelegramEnvio::enviados($resultados));
        Http::assertSentCount(3);
        foreach (['111', '222', '333'] as $chatId) {
            Http::assertSent(fn (Request $request) => $request->url() === 'https://api.telegram.org/botTOKEN/sendMessage'
                && $request['chat_id'] === $chatId
                && $request['text'] === 'Hola'
                && $request['parse_mode'] === 'Markdown');
        }
    }

    public function test_texto_usa_3_s_de_conexion_y_8_s_de_respuesta(): void
    {
        $this->fakeTelegram();

        app(TelegramEnvio::class)->mensaje(['111', '222'], 'Hola');

        $this->assertCount(2, $this->opciones);
        foreach ($this->opciones as $opcion) {
            $this->assertSame(3, $opcion['connect_timeout']);
            $this->assertSame(8, $opcion['timeout']);
        }
    }

    public function test_un_archivo_sube_una_vez_y_los_demas_lo_reciben_por_file_id(): void
    {
        $this->fakeArchivo();

        $resultados = app(TelegramEnvio::class)->archivo('sendDocument', ['111', '222', '333'], 'JPGDATA', 'corte.jpg', 'Pie');

        $this->assertSame(3, TelegramEnvio::enviados($resultados));
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => $request->isMultipart()
            && self::parte($request, 'chat_id') === '111'
            && self::parte($request, 'caption') === 'Pie'
            && self::parte($request, 'document') === 'JPGDATA');
        foreach (['222', '333'] as $chatId) {
            Http::assertSent(fn (Request $request) => ! $request->isMultipart()
                && $request->url() === 'https://api.telegram.org/botTOKEN/sendDocument'
                && $request['chat_id'] === $chatId
                && $request['caption'] === 'Pie'
                && $request['document'] === 'FILE-111');
        }
        $this->assertSame(1, collect($this->opciones)->where('timeout', 30)->count());
        $this->assertSame(2, collect($this->opciones)->where('timeout', 8)->count());
    }

    public function test_una_foto_se_reenvia_con_el_file_id_de_mayor_resolucion(): void
    {
        $this->fakeArchivo();

        app(TelegramEnvio::class)->archivo('sendPhoto', ['111', '222'], 'PNGDATA', 'corte.png', 'Pie');

        Http::assertSent(fn (Request $request) => ! $request->isMultipart()
            && $request['chat_id'] === '222'
            && $request['photo'] === 'GRANDE-111');
    }

    public function test_si_el_primer_chat_rechaza_el_archivo_se_sube_al_siguiente(): void
    {
        $this->fakeArchivo(rechaza: ['111']);

        $resultados = app(TelegramEnvio::class)->archivo('sendDocument', ['111', '222', '333'], 'JPGDATA', 'corte.jpg', 'Pie');

        $this->assertFalse(TelegramEnvio::exitoso($resultados['111']));
        $this->assertSame(2, TelegramEnvio::enviados($resultados));
        Http::assertSent(fn (Request $request) => ! $request->isMultipart()
            && $request['chat_id'] === '333'
            && $request['document'] === 'FILE-222');
    }

    public function test_si_la_subida_se_vence_no_se_vuelve_a_subir(): void
    {
        Http::fake(fn () => Http::failedConnection());

        $resultados = app(TelegramEnvio::class)->archivo('sendDocument', ['111', '222', '333'], 'JPGDATA', 'corte.jpg', 'Pie');

        Http::assertSentCount(1);
        $this->assertSame(['111', '222', '333'], array_map('strval', array_keys($resultados)));
        $this->assertSame(0, TelegramEnvio::enviados($resultados));
    }

    public function test_un_reenvio_con_falla_pasajera_se_reintenta_una_vez(): void
    {
        $intentos = 0;
        Http::fake(function (Request $request) use (&$intentos) {
            if ($request->isMultipart()) {
                return Http::response(['ok' => true, 'result' => ['document' => ['file_id' => 'FILE']]]);
            }

            return ++$intentos === 1 ? Http::response(['ok' => false], 502) : Http::response(['ok' => true, 'result' => []]);
        });

        $resultados = app(TelegramEnvio::class)->archivo('sendDocument', ['111', '222'], 'JPGDATA', 'corte.jpg', 'Pie');

        $this->assertSame(2, TelegramEnvio::enviados($resultados));
        $this->assertSame(2, $intentos);
    }

    public function test_un_chat_caido_no_impide_que_los_demas_reciban(): void
    {
        Http::fake(fn (Request $request) => match ($request['chat_id']) {
            'caido' => Http::failedConnection(),
            'rechaza' => Http::response(['ok' => false, 'description' => 'chat not found'], 400),
            default => Http::response(['ok' => true]),
        });

        $resultados = app(TelegramEnvio::class)->mensaje(['caido', 'rechaza', 'bien'], 'Hola');

        $this->assertInstanceOf(ConnectionException::class, $resultados['caido']);
        $this->assertTrue(TelegramEnvio::reintentable($resultados['caido']));
        $this->assertInstanceOf(Response::class, $resultados['rechaza']);
        $this->assertFalse(TelegramEnvio::reintentable($resultados['rechaza']));
        $this->assertTrue(TelegramEnvio::exitoso($resultados['bien']));
        $this->assertSame(1, TelegramEnvio::enviados($resultados));
    }

    public function test_el_detalle_de_un_error_no_expone_el_token(): void
    {
        $error = new ConnectionException('cURL error 28: timed out for https://api.telegram.org/bot123:ABC-secreto/sendPhoto');

        $respuesta = TelegramEnvio::detalle($error)['response'];

        $this->assertStringNotContainsString('ABC-secreto', $respuesta);
        $this->assertStringContainsString('/bot***/sendPhoto', $respuesta);
    }

    public function test_sin_chats_no_hay_peticiones(): void
    {
        Http::fake();

        $this->assertSame([], app(TelegramEnvio::class)->mensaje([], 'Hola'));
        Http::assertNothingSent();
    }

    public function test_el_resumen_dice_a_cuantos_llego(): void
    {
        $this->assertSame('Reporte enviado por Telegram a 2 de 3 destinatarios', TelegramEnvio::resumen('Reporte', 2, 3));
        $this->assertSame('Imagen enviada por Telegram a 1 de 1 destinatario', TelegramEnvio::resumen('Imagen', 1, 1, femenino: true));
        $this->assertSame('Reporte: no se pudo enviar por Telegram (0 de 2 destinatarios)', TelegramEnvio::resumen('Reporte', 0, 2));
        $this->assertStringContainsString('sin destinatarios activos', TelegramEnvio::resumen('Reporte', 0, 0));
    }

    private function fakeTelegram(): void
    {
        Http::fake(function (Request $request, array $options) {
            $this->opciones[] = $options;

            return Http::response(['ok' => true, 'result' => []]);
        });
    }

    /**
     * Telegram simulado para archivos: la subida (multipart) devuelve el file_id
     * "FILE-{chat}" (en fotos, varias resoluciones) y el reenvío por file_id responde ok.
     *
     * @param  list<string>  $rechaza  Chats que responden 403 a la subida.
     */
    private function fakeArchivo(array $rechaza = []): void
    {
        Http::fake(function (Request $request, array $options) use ($rechaza) {
            $this->opciones[] = $options;
            if (! $request->isMultipart()) {
                return Http::response(['ok' => true, 'result' => []]);
            }

            $chatId = self::parte($request, 'chat_id');
            if (in_array($chatId, $rechaza, true)) {
                return Http::response(['ok' => false, 'description' => 'bot was blocked by the user'], 403);
            }

            return Http::response(['ok' => true, 'result' => [
                'document' => ['file_id' => "FILE-{$chatId}"],
                'photo' => [['file_id' => "CHICA-{$chatId}"], ['file_id' => "GRANDE-{$chatId}"]],
            ]]);
        });
    }

    private static function parte(Request $request, string $nombre): mixed
    {
        return collect($request->data())->pluck('contents', 'name')[$nombre] ?? null;
    }
}
