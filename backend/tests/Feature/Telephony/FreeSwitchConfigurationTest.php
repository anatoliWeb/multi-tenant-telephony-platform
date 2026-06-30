<?php

namespace Tests\Feature\Telephony;

use Tests\TestCase;

class FreeSwitchConfigurationTest extends TestCase
{
    public function test_optional_freeswitch_config_exposes_safe_local_defaults(): void
    {
        $this->assertFalse((bool) config('freeswitch.enabled'));
        $this->assertSame('freeswitch', config('freeswitch.host'));
        $this->assertSame(5060, config('freeswitch.ports.sip'));
        $this->assertSame(5061, config('freeswitch.ports.sip_tls'));
        $this->assertSame(7443, config('freeswitch.ports.wss'));
        $this->assertSame(16384, config('freeswitch.ports.rtp_start'));
        $this->assertSame(32768, config('freeswitch.ports.rtp_end'));
        $this->assertSame(8021, config('freeswitch.ports.event_socket'));
        $this->assertSame('/etc/freeswitch', config('freeswitch.paths.config'));
        $this->assertSame('/var/lib/freeswitch/recordings', config('freeswitch.paths.recordings'));
        $this->assertSame('/var/log/freeswitch', config('freeswitch.paths.logs'));
        $this->assertSame('/etc/freeswitch/tls', config('freeswitch.paths.tls'));
    }
}
