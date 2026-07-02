export type SipCallState =
  | 'idle'
  | 'checking_permissions'
  | 'ready'
  | 'registering'
  | 'registered'
  | 'registration_failed'
  | 'dialing'
  | 'ringing'
  | 'active'
  | 'held'
  | 'ended'
  | 'failed';

export type SipRegistrationState = 'disconnected' | 'connecting' | 'registered' | 'failed';

export type MicrophonePermissionState = 'unknown' | 'checking' | 'granted' | 'denied' | 'prompt' | 'unsupported';

export interface SipMediaDiagnostics {
  remote_audio_attached: boolean;
  remote_audio_track_count: number;
  remote_audio_playing: boolean;
  peer_connection_state: RTCPeerConnectionState | 'unknown';
  ice_connection_state: RTCIceConnectionState | 'unknown';
  last_media_error: string | null;
}

export interface SipBrowserDiagnostics {
  browser_name: string;
  is_opera: boolean;
  has_media_devices: boolean;
  has_get_user_media: boolean;
  has_peer_connection: boolean;
  audio_autoplay_supported: boolean | 'unknown';
  warning_message: string | null;
}

export interface SipAudioInputDevice {
  device_id: string;
  label: string;
  is_default: boolean;
}

export interface SipProfileCapabilities {
  outbound_call: boolean;
  inbound_call: boolean;
  hold: boolean;
  mute: boolean;
}

export interface SipProfileRegistrationState {
  enabled: boolean;
  state: 'disabled' | 'available' | 'connecting' | 'registered' | 'failed';
  reason: string | null;
}

export interface SipProfile {
  extension_id: number;
  extension_number: string;
  display_name: string;
  sip_uri: string;
  authorization_username: string;
  password?: string | null;
  websocket_url: string;
  domain: string;
  provider: string;
  expires_seconds: number;
  credentials_available: boolean;
  registration_enabled: boolean;
  local_demo_mode: boolean;
  capabilities: SipProfileCapabilities;
  registration: SipProfileRegistrationState;
  tenant_id: string;
}
