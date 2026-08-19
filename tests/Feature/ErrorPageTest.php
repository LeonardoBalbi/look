<?php

namespace Tests\Feature;

use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    public function test_minimal_error_page_is_safe_without_explicit_variables(): void
    {
        $html = view('errors.minimal')->render();

        $this->assertStringContainsString('Não foi possível concluir', $html);
        $this->assertStringContainsString('500', $html);
    }

    public function test_expired_page_has_a_clear_message(): void
    {
        $html = view('errors.419')->render();

        $this->assertStringContainsString('Página expirada', $html);
        $this->assertStringContainsString('Atualize a página', $html);
    }
}
