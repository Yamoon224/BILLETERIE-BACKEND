<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API Billetterie interurbaine</title>
    <style>
        :root {
            --orange: #ff8200;
            --orange-dark: #ef6c00;
            --ink: #1a1a1a;
            --muted: #5b6270;
            --line: #e8e6e3;
            --surface: #ffffff;
            --canvas: #fdfaf6;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --ink: #f4f1ec;
                --muted: #a2a8b4;
                --line: #2b2f36;
                --surface: #171a1f;
                --canvas: #0f1114;
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 3rem 1.25rem;
            background: var(--canvas);
            color: var(--ink);
            font: 15px/1.6 ui-sans-serif, system-ui, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .shell { max-width: 46rem; margin: 0 auto; }

        .badge {
            display: inline-block;
            padding: .25rem .6rem;
            border-radius: 2px;
            background: linear-gradient(135deg, #ff9a3c 0%, var(--orange) 48%, var(--orange-dark) 100%);
            color: #fff;
            font-size: .7rem;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        h1 { margin: 1rem 0 .25rem; font-size: 1.6rem; letter-spacing: -.02em; }

        .rule {
            display: block;
            width: 3.5rem;
            height: 3px;
            margin: .75rem 0 1.25rem;
            border-radius: 999px;
            background: linear-gradient(135deg, #ff9a3c, var(--orange-dark));
        }

        p { color: var(--muted); margin: 0 0 1rem; }

        .card {
            margin-top: 2rem;
            border: 1px solid var(--line);
            border-radius: 2px;
            background: var(--surface);
            overflow: hidden;
        }

        .card > div { padding: 1.1rem 1.25rem; }
        .card > div + div { border-top: 1px solid var(--line); }
        .card .accent { padding: 0; height: 3px; background: linear-gradient(135deg, #ff9a3c, var(--orange-dark)); }

        h2 { margin: 0 0 .4rem; font-size: .95rem; }

        a { color: var(--orange-dark); font-weight: 500; }

        code {
            padding: .1rem .35rem;
            border-radius: 2px;
            background: rgba(255, 130, 0, .1);
            font-family: ui-monospace, "Cascadia Code", Menlo, monospace;
            font-size: .85em;
        }

        footer { margin-top: 2.5rem; color: var(--muted); font-size: .85rem; }
    </style>
</head>
<body>
    <main class="shell">
        <span class="badge">API REST</span>
        <h1>Billetterie de transport interurbain</h1>
        <span class="rule"></span>
        <p>
            Backend de la plateforme : reservation en ligne, vente au guichet,
            validation des billets a l'embarquement et suivi d'activite des
            compagnies partenaires.
        </p>

        <div class="card">
            <div class="accent"></div>
            <div>
                <h2>Documentation de l'API</h2>
                <p style="margin:0">
                    Specification OpenAPI et bac a sable : <a href="{{ route('docs') }}">/docs</a>
                    &middot; format brut : <a href="{{ route('docs.openapi') }}">/docs/openapi.json</a>
                </p>
            </div>
            <div>
                <h2>Sonde de sante</h2>
                <p style="margin:0">
                    <code>GET /api/health</code> - verifie la base de donnees et la
                    presence de la cle de signature des billets.
                </p>
            </div>
            <div>
                <h2>Interfaces</h2>
                <p style="margin:0">
                    L'interface voyageur et le tableau de bord sont servis par
                    l'application Next.js du dossier <code>web/</code>.
                </p>
            </div>
        </div>

        <footer>
            Environnement : <code>{{ app()->environment() }}</code> &middot;
            Laravel {{ app()->version() }}
        </footer>
    </main>
</body>
</html>
