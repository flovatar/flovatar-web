<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark light">
<meta name="supported-color-schemes" content="dark light">
<title>{{ $code }} is your Flovatar code</title>
</head>
<body style="margin:0;padding:0;background:#180830;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">Your Flovatar code is {{ $code }}. It works for {{ $minutes }} minutes.</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#180830;">
  <tr>
    <td align="center" style="padding:32px 16px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:480px;">
        <tr>
          <td align="center" style="padding:0 0 24px;">
            <img src="https://brand.flovatar.com/assets/logos/flovatar/png/flovatar-logo-horizontal-white.png" width="180" alt="FLOVATAR" style="display:block;width:180px;max-width:60%;height:auto;border:0;color:#FFFFFF;font:700 24px 'Arial Narrow',Arial,sans-serif;">
          </td>
        </tr>
        <tr>
          <td style="background:#343B5A;border:2px solid #1A1E2D;border-radius:10px;padding:28px 24px;font-family:'Roboto Condensed','Arial Narrow',Arial,sans-serif;color:#FFFFFF;">
            <p style="margin:0 0 8px;font-size:22px;line-height:1.25;font-weight:700;color:#AED7F7;">Your code</p>
            <p style="margin:0 0 20px;font-size:16px;line-height:1.4;color:#FFFFFF;">{{ $intro }}</p>
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
              <tr>
                <td align="center" style="background:#1A1E2D;border-radius:10px;padding:18px 8px;">
                  <span style="font-family:'Courier New',Courier,monospace;font-size:36px;line-height:1;font-weight:700;letter-spacing:10px;color:#FFEE50;">{{ $code }}</span>
                </td>
              </tr>
            </table>
            <p style="margin:20px 0 0;font-size:15px;line-height:1.4;color:#FFFFFF;">It works for <b>{{ $minutes }} minutes</b> and only once. Enter it in the window where you asked for it.</p>
            <p style="margin:16px 0 0;font-size:14px;line-height:1.4;color:#9DA4C7;">Didn't ask for this? You can ignore this email. Nobody can get into an account with this code alone, and we never ask for it by phone or chat.</p>
          </td>
        </tr>
@if($testnet)
        <tr>
          <td align="center" style="padding:16px 8px 0;font-family:'Roboto Condensed','Arial Narrow',Arial,sans-serif;font-size:13px;line-height:1.4;color:#F84F64;">This email comes from the Flovatar test site (Flow testnet). Nothing on it is real.</td>
        </tr>
@endif
        <tr>
          <td align="center" style="padding:24px 8px 0;font-family:'Roboto Condensed','Arial Narrow',Arial,sans-serif;font-size:13px;line-height:1.4;color:#9DA4C7;">
            Flovatar · <a href="{{ $site }}" style="color:#91E3F9;text-decoration:none;">{{ preg_replace('#^https?://#', '', $site) }}</a>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
