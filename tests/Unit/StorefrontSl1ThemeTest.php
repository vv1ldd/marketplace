<?php

namespace Tests\Unit;

use App\Support\StorefrontSl1Theme;
use Illuminate\Http\Request;
use Tests\TestCase;

class StorefrontSl1ThemeTest extends TestCase
{
    public function test_meanly_authorize_query_preserves_embedded_iframe_flag(): void
    {
        $request = Request::create('/authorize', 'GET', [
            'client_name' => 'Meanly',
            'iframe' => '1',
        ]);

        $query = StorefrontSl1Theme::augmentAuthorizeQuery([
            'client_name' => 'Meanly',
            'iframe' => '1',
        ], $request);

        $this->assertSame('1', $query['iframe']);
    }

    public function test_meanly_authorize_query_strips_holiday_even_with_cookie(): void
    {
        $request = Request::create('/authorize', 'GET', [
            'client_name' => 'Meanly',
            'ui_theme' => 'neobrutalism',
            'holiday' => 'national-unity',
        ]);
        $request->cookies->set('holiday', 'national-unity');

        $query = StorefrontSl1Theme::augmentAuthorizeQuery([
            'client_name' => 'Meanly',
            'ui_theme' => 'neobrutalism',
            'holiday' => 'national-unity',
        ], $request);

        $this->assertArrayNotHasKey('holiday', $query);
    }

    public function test_maestrooo_authorize_query_keeps_holiday_from_cookie(): void
    {
        $request = Request::create('/authorize', 'GET', [
            'client_name' => 'Maestrooo',
            'ui_theme' => 'dark',
        ]);
        $request->cookies->set('holiday', 'national-unity');

        $query = StorefrontSl1Theme::augmentAuthorizeQuery([
            'client_name' => 'Maestrooo',
            'ui_theme' => 'dark',
        ], $request);

        $this->assertSame('national-unity', $query['holiday']);
    }

    public function test_maestrooo_context_detected_from_client_app_param(): void
    {
        $request = Request::create('/authorize', 'GET', [
            'client_app' => 'maestrooo',
        ]);
        $request->cookies->set('holiday', 'halloween');

        $query = StorefrontSl1Theme::augmentAuthorizeQuery([], $request);

        $this->assertSame('halloween', $query['holiday']);
    }
}
