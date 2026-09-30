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
 * PERF-13: un envÃ­o a N chats sale en paralelo y con timeouts cortos.
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

    public function test_un_archivo_usa_45_s_y_va_como_multipart(): void
    {
        $this->fakeTelegram();

        $resultados = app(TelegramEnvio::class)->archivo('sendPhoto', ['111', '222'], 'PNGDATA', 'corte.png', 'Pie');

        $this->assertSame(2, TelegramEnvio::enviados($resultados));
        foreach ($this->opciones as $opcion) {
            $this->assertSame(3, $opcion['connect_timeout']);
            $this->assertSame(45, $opcion['timeout']);
        }
        Http::assertSent(function (Request $request) {
            $partes = collect($request->data())->pluck('contents', 'name');

            return $request->url() === 'https://api.telegram.org/botTOKEN/sendPhoto'
                && $request->isMultipart()
                && $partes['chat_id'] === '111'
                && $partes['caption'] === 'Pie'
                && $partes['photo'] === 'PNGDATA';
        });
    }

    public function test_el_archivo_se_sube_una_vez_y_los_demas_reciben_el_file_id(): void
    {
        Http::fake(fn (Request $request) => Http::response(['ok' => true, 'result' => ['document' => ['file_id' => 'FID']]]));

        $resultados = app(TelegramEnvio::class)->archivo('sendDocument', ['111', '222', '333'], 'JPGDATA', 'corte.jpg', 'Pie');

        $this->assertSame(3, TelegramEnvio::enviados($resultados));
        Http::assertSentCount(3);
        $subidas = Http::recorded(fn (Request $request) => $request->isMultipart());
        $this->assertCount(1, $subidas);
        foreach (['222', '333'] as $chatId) {
            Http::assertSent(fn (Request $request) => ! $request->isMultipart()
                && $request['chat_id'] === $chatId
                && $request['document'] === 'FID'
                && $request['caption'] === 'Pie');
        }
    }

    public function test_con_un_solo_chat_hay_una_sola_peticion(): void
    {
        $this->fakeTelegram();

        $resultados = app(TelegramEnvio::class)->archivo('sendDocument', ['111', '111'], 'JPGDATA', 'corte.jpg', 'Pie');

        $this->assertCount(1, $resultados);
        Http::assertSentCount(1);
    }

    public function test_si_el_primer_chat_rechaza_los_demas_reciben_el_archivo_completo(): void
    {
        Http::fake(fn (Request $request) => $this->chatDe($request) === 'bloqueado'
            ? Http::response(['ok' => false, 'description' => 'bot was blocked by the user'], 403)
            : Http::response(['ok' => true, 'result' => ['document' => ['file_id' => 'FID']]]));

        $resultados = app(TelegramEnvio::class)->archivo('sendDocument', ['bloqueado', '222', '333'], 'JPGDATA', 'corte.jpg', 'Pie');

        $this->assertFalse(TelegramEnvio::exitoso($resultados['bloqueado']));
        $this->assertSame(2, TelegramEnvio::enviados($resultados));
        $this->assertCount(3, Http::recorded(fn (Request $request) => $request->isMultipart()));
    }

    public function test_si_el_primer_chat_no_conecta_los_demas_se_intentan_igual(): void
    {
        Http::fake(fn (Request $request) => $this->chatDe($request) === 'caido'
            ? Http::failedConnection()
            : Http::response(['ok' => true, 'result' => ['document' => ['file_id' => 'FID']]]));

        $resultados = app(TelegramEnvio::class)->archivo('sendDocument', ['caido', '222'], 'JPGDATA', 'corte.jpg', 'Pie');

        $this->assertInstanceOf(ConnectionException::class, $resultados['caido']);
        $this->assertTrue(TelegramEnvio::exitoso($resultados['222']));
    }

    public function test_si_falla_el_reenvio_por_file_id_se_reporta_ese_chat(): void
    {
        Http::fake(fn (Request $request) => $request->isMultipart()
            ? Http::response(['ok' => true, 'result' => ['document' => ['file_id' => 'FID']]])
            : Http::response(['ok' => false, 'description' => 'chat not found'], 400));

        $resultados = app(TelegramEnvio::class)->archivo('sendDocument', ['111', '222'], 'JPGDATA', 'corte.jpg', 'Pie');

        $this->assertTrue(TelegramEnvio::exitoso($resultados['111']));
        $this->assertFalse(TelegramEnvio::exitoso($resultados['222']));
        $this->assertSame(1, TelegramEnvio::enviados($resultados));
    }

    public function test_en_sendphoto_se_reusa_el_file_id_del_tamano_original(): void
    {
        Http::fake(fn () => Http::response(['ok' => true, 'result' => ['photo' => [
            ['file_id' => 'CHICA', 'width' => 90],
            ['file_id' => 'ORIGINAL', 'width' => 1280],
        ]]]));

        app(TelegramEnvio::class)->archivo('sendPhoto', ['111', '222'], 'PNGDATA', 'corte.png', 'Pie');

        Http::assertSent(fn (Request $request) => ! $request->isMultipart()
            && $request['chat_id'] === '222'
            && $request['photo'] === 'ORIGINAL');
    }

    public function test_el_reenvio_por_file_id_usa_el_timeout_de_texto(): void
    {
        Http::fake(function (Request $request, array $options) {
            if (! $request->isMultipart()) {
                $this->opciones[] = $options;
            }

            return Http::response(['ok' => true, 'result' => ['document' => ['file_id' => 'FID']]]);
        });

        app(TelegramEnvio::class)->archivo('sendDocument', ['111', '222'], 'JPGDATA', 'corte.jpg', 'Pie');

        $this->assertCount(1, $this->opciones);
        $this->assertSame(TelegramEnvio::SEGUNDOS_TEXTO, $this->opciones[0]['timeout']);
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

    private function chatDe(Request $request): ?string
    {
        return $request->isMultipart()
            ? collect($request->data())->pluck('contents', 'name')['chat_id'] ?? null
            : $request['chat_id'];
    }

    private function fakeTelegram(): void
    {
        Http::fake(function (Request $request, array $options) {
            $this->opciones[] = $options;

            return Http::response(['ok' => true, 'result' => []]);
        });
    }
}
