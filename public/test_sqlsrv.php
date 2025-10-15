<?php
try {
  $cn = new PDO("sqlsrv:Server=192.168.2.28,1433;Database=ProdTowel;TrustServerCertificate=True","","");
  $cn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  echo "Conectó OK";
} catch (Throwable $e) {
  echo "Fallo: " . $e->getMessage();
}
