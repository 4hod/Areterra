<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page not found — Areterra Hub</title>
    <style>
        :root { color-scheme: light; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f2f7f8; color: #00345c; }
        main { width: min(32rem, calc(100% - 2rem)); padding: 3rem 2rem; box-sizing: border-box; border: 1px solid #d7e3e8; border-radius: 1.5rem; background: white; text-align: center; box-shadow: 0 1.5rem 4rem rgba(0, 52, 92, .12); }
        .code { color: #009de6; font-size: .8rem; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
        h1 { margin: .5rem 0; font-size: clamp(1.8rem, 8vw, 2.6rem); }
        p { margin: 0 auto 1.5rem; color: #5f7180; line-height: 1.6; }
        a { display: inline-block; min-height: 2.75rem; padding: .75rem 1.25rem; box-sizing: border-box; border-radius: 999px; background: #009de6; color: white; font-weight: 750; text-decoration: none; }
        a:focus-visible { outline: 3px solid #00345c; outline-offset: 3px; }
    </style>
</head>
<body>
    <main>
        <div class="code">Error 404</div>
        <h1>We couldn't find that page</h1>
        <p>The link may be out of date, or the page may have moved. Return to the Hub and try again.</p>
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}">Return to {{ auth()->check() ? 'the Hub' : 'sign in' }}</a>
    </main>
</body>
</html>
