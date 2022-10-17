<?php

namespace Tests\Modifiers;

use Carbon\Carbon;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Modifiers\Modify;
use Tests\TestCase;

class TimezoneTest extends TestCase
{
    /** @test */
    public function it_applies_timezone(): void
    {
        $modified = $this->modify(Carbon::parse('2022-10-10 12:00'), ['America/New_York']);
        $this->assertEquals('2022 Oct 10 08:00 -0400', $modified->format('Y M d H:i O'));
    }

    /** @test */
    public function it_applies_app_timezone_when_empty(): void
    {
        $modified = $this->modify(Carbon::parse('2022-10-10 12:00'));
        $this->assertEquals('2022 Oct 10 12:00 +0000', $modified->format('Y M d H:i O'));
    }

    /** @test */
    public function it_applies_app_timezone(): void
    {
        $modified = $this->modify(Carbon::parse('2022-10-10 12:00'), ['app']);
        $this->assertEquals('2022 Oct 10 12:00 +0000', $modified->format('Y M d H:i O'));
    }

    /** @test */
    public function it_applies_site_timezone(): void
    {
        Site::setConfig(['sites' => [
            'en' => ['timezone' => 'Europe/London', 'url'=> 'http://localhost/'],
        ]]);

        $modified = $this->modify(Carbon::parse('2022-10-10 12:00'), ['site']);
        $this->assertEquals('2022 Oct 10 13:00 +0100', $modified->format('Y M d H:i O'));
    }

    /** @test */
    public function it_applies_user_timezone(): void
    {
        $this->actingAs(User::make()->setPreferredTimezone('Europe/Berlin'));

        $modified = $this->modify(Carbon::parse('2022-10-10 12:00'), ['user']);
        $this->assertEquals('2022 Oct 10 14:00 +0200', $modified->format('Y M d H:i O'));
    }

    private function modify($value, array $params = [])
    {
        return Modify::value($value)->timezone($params)->fetch();
    }
}
