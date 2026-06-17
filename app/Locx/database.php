<?php

use Illuminate\Support\Facades\DB;

try {
    $pdo = DB::connection()->getPdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    die('Erro ao conectar no banco pelo Laravel: '.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
