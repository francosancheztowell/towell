<?php
// Crea el esquema sqlite a partir de los modelos ($fillable + $casts + PK) y siembra datos mínimos.
require __DIR__.'/boot.php';
@unlink(HARNESS_DIR.'/main.sqlite'); @unlink(HARNESS_DIR.'/dbo.sqlite');
touch(HARNESS_DIR.'/main.sqlite'); touch(HARNESS_DIR.'/dbo.sqlite');
$app = require REPO.'/bootstrap/app.php';
$app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class, fn () => harness_config());
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
harness_attach();
use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlite');
function crear(string $tabla, array $cols, ?string $pk): void {
    global $db;
    if (! $cols) { $cols = ["Id" => "INTEGER"]; }
    [$schema, $name] = str_contains($tabla, '.') ? explode('.', $tabla, 2) : ['main', $tabla];
    $schema = strtolower($schema) === 'dbo' ? 'dbo' : 'main';
    $defs = [];
    foreach ($cols as $c => $t) {
        $defs[] = '"'.$c.'" '.($c === $pk ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : $t);
    }
    $db->statement("CREATE TABLE IF NOT EXISTS $schema.\"$name\" (".implode(', ', $defs).')');
}
$extra = @json_decode(@file_get_contents(ARNES.'/extra-cols.json') ?: '{}', true) ?: [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(REPO.'/app/Models'));
foreach ($it as $f) {
    if ($f->getExtension() !== 'php') continue;
    $rel = substr($f->getPathname(), strlen(REPO.'/app/Models/'), -4);
    $cls = 'App\\Models\\'.str_replace('/', '\\', $rel);
    if (! class_exists($cls)) continue;
    $r = new ReflectionClass($cls);
    if ($r->isAbstract() || ! $r->isSubclassOf(Illuminate\Database\Eloquent\Model::class)) continue;
    try { $m = $r->newInstance(); } catch (Throwable $e) { continue; }
    $pk = $m->getKeyName();
    $casts = $m->getCasts();
    $cols = [];
    $nombres = array_unique(array_merge($pk ? [$pk] : [], $m->getFillable(), array_keys($casts), $extra[$m->getTable()] ?? []));
    foreach ($nombres as $c) {
        $t = strtok((string) ($casts[$c] ?? 'string'), ':');
        $cols[$c] = match (true) {
            in_array($t, ['int','integer','bool','boolean'], true) => 'INTEGER',
            in_array($t, ['float','double','real','decimal'], true) => 'REAL',
            default => 'TEXT',
        };
    }
    if ($m->usesTimestamps()) { $cols['created_at'] ??= 'TEXT'; $cols['updated_at'] ??= 'TEXT'; }
    crear($m->getTable(), $cols, $m->getIncrementing() ? $pk : null);
    // Espejo sin/con dbo para SQL crudo que escribe el prefijo a mano.
    $t = $m->getTable();
    crear(str_starts_with(strtolower($t), 'dbo.') ? substr($t, 4) : 'dbo.'.$t, $cols, $m->getIncrementing() ? $pk : null);
}
require ARNES.'/seed.php';
echo "listo\n";
