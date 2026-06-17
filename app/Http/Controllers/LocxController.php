<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class LocxController extends Controller
{
    public function index(): Response { return $this->render('index.php'); }
    public function login(): Response { return $this->render('login.php'); }
    public function logout(): Response { return $this->render('logout.php'); }
    public function webhookPagbank(): Response { return $this->render('webhooks/pagbank.php'); }
    public function webhookWhatsapp(): Response { return $this->render('webhooks/whatsapp.php'); }

    private function render(string $file): Response
    {
        $safe = str_replace(['../', '..\\'], '', $file);
        $path = resource_path('views/locx/'.$safe);
        abort_unless(is_file($path), 404);

        $oldScriptName = $_SERVER['SCRIPT_NAME'] ?? null;
        $oldPhpSelf = $_SERVER['PHP_SELF'] ?? null;
        $_SERVER['SCRIPT_NAME'] = '/locx/'.ltrim(str_replace('\\', '/', $safe), '/');
        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];

        ob_start();
        try {
            require $path;
            $html = ob_get_clean();
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            if ($oldScriptName !== null) { $_SERVER['SCRIPT_NAME'] = $oldScriptName; }
            if ($oldPhpSelf !== null) { $_SERVER['PHP_SELF'] = $oldPhpSelf; }
            throw $e;
        }

        if ($oldScriptName !== null) { $_SERVER['SCRIPT_NAME'] = $oldScriptName; }
        if ($oldPhpSelf !== null) { $_SERVER['PHP_SELF'] = $oldPhpSelf; }

        return response($html);
    }
}
