<?php

/*
|--------------------------------------------------------------------------
| Trusted proxies
|--------------------------------------------------------------------------
| When the app sits behind a proxy, every request appears to come from the
| proxy, so per-visitor rate limits would be shared by all visitors. Trusting
| the proxy makes request()->ip() read the visitor's address from the
| X-Forwarded-For header instead.
|
| Leave TRUSTED_PROXIES empty unless the owner's System page shows a proxy
| address instead of your own. "*" trusts whichever proxy forwarded the
| request (its REMOTE_ADDR); a comma-separated list trusts only those IPs.
*/

return [

    'proxies' => env('TRUSTED_PROXIES'),

];
