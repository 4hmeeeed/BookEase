<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The privacy notice (RA 10173): public, reachable from every public page and
 * from sign-up, and plainly a draft until the owner supplies the wording.
 */
class PrivacyNoticePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_read_the_privacy_notice(): void
    {
        $this->get('/privacy')->assertOk()
            ->assertSee('Privacy notice')
            ->assertSee('Republic Act No. 10173')
            ->assertSee('National Privacy Commission');
    }

    public function test_the_notice_is_final_with_no_placeholders_left(): void
    {
        // assertOk first: an error page quotes this file, words and all.
        $this->get('/privacy')->assertOk()
            ->assertDontSee('Draft notice')
            ->assertDontSee('Owner to supply')
            ->assertSee('Last updated: November 22, 2026')
            ->assertSee('data protection officer');
    }

    public function test_it_says_how_guests_pay_in_the_current_mode(): void
    {
        config(['bookease.payments.online' => false]);
        $this->get('/privacy')->assertOk()->assertSee('You pay at the front desk')->assertDontSee('GCash');

        config(['bookease.payments.online' => true]);
        $this->get('/privacy')->assertOk()->assertSee('GCash or Maya login details')->assertDontSee('You pay at the front desk');
    }

    public function test_every_public_page_links_to_it(): void
    {
        $this->get('/')->assertSee('href="'.route('privacy').'"', false);
    }

    public function test_sign_up_asks_for_consent_to_the_notice(): void
    {
        $html = $this->get('/register')->assertOk()->getContent();

        $tag = $this->consentCheckbox($html);
        $this->assertStringContainsString('type="checkbox"', $tag);
        $this->assertStringContainsString('value="1"', $tag);
        $this->assertMatchesRegularExpression('/\srequired[\s>=]/', $tag);
        $this->assertDoesNotMatchRegularExpression('/\schecked[\s>=]/', $tag, 'Consent must not be ticked for the guest.');
        $this->assertStringContainsString('href="'.route('privacy').'"', $html);
    }

    public function test_the_consent_tick_survives_a_failed_sign_up(): void
    {
        $html = $this->withSession(['_old_input' => ['privacy_consent' => '1']])
            ->get('/register')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/\schecked[\s>=]/', $this->consentCheckbox($html));
    }

    public function test_google_sign_in_says_continuing_means_agreeing(): void
    {
        config(['services.google.client_id' => 'test-client', 'services.google.client_secret' => 'test-secret']);

        // Google creates an account on first sign-in from either page.
        foreach (['/login', '/register'] as $page) {
            $this->get($page)->assertOk()->assertSee('By continuing you agree to the');
        }
    }

    private function consentCheckbox(string $html): string
    {
        $this->assertSame(1, preg_match('/<input\b[^>]*\bname="privacy_consent"[^>]*>/', $html, $tag), 'No privacy_consent input on the page.');

        return $tag[0];
    }
}
