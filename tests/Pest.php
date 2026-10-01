<?php

use Tests\TestCase;

// ponytail: Pest solo para los tests de navegador (fuera de las suites de phpunit.xml: corren con
// `vendor/bin/pest tests/Browser`); el resto sigue siendo PHPUnit y Pest lo corre igual.
pest()->extend(TestCase::class)->in('Browser');
