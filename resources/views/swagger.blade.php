<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>Catholic Tanzania Core API — Swagger UI</title>
        <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.32.15/swagger-ui.css" crossorigin="anonymous">
        <style>
            html { box-sizing: border-box; overflow-y: scroll; }
            *, *::before, *::after { box-sizing: inherit; }
            body { margin: 0; background: #fafafa; }
            .swagger-ui .topbar { background: #5b171d; }
            .swagger-ui .topbar .download-url-wrapper .select-label { color: #fff; }
            .swagger-ui .info .title small { background: #5b171d; }
        </style>
    </head>
    <body>
        <div id="swagger-ui"></div>
        <script src="https://unpkg.com/swagger-ui-dist@5.32.15/swagger-ui-bundle.js" crossorigin="anonymous"></script>
        <script src="https://unpkg.com/swagger-ui-dist@5.32.15/swagger-ui-standalone-preset.js" crossorigin="anonymous"></script>
        <script>
            window.addEventListener('load', () => {
                window.ui = SwaggerUIBundle({
                    url: @json(route('openapi')),
                    dom_id: '#swagger-ui',
                    deepLinking: true,
                    displayRequestDuration: true,
                    filter: true,
                    persistAuthorization: true,
                    docExpansion: 'none',
                    presets: [
                        SwaggerUIBundle.presets.apis,
                        SwaggerUIStandalonePreset,
                    ],
                    layout: 'StandaloneLayout',
                });
            });
        </script>
    </body>
</html>
