<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Documentation API — Billetterie interurbaine</title>

    {{--
        Swagger UI est servi depuis nos propres assets et non depuis un CDN :
        la documentation reste consultable sans acces Internet — ce qui compte
        pour une equipe qui travaille sur une liaison instable — et aucune
        ressource tierce ne peut etre substituee a notre insu. Les fichiers
        sont copies depuis swagger-ui-dist par `composer run docs:assets`.
    --}}
    <link rel="stylesheet" href="{{ asset('vendor/swagger-ui/swagger-ui.css') }}">

    <style>
        /*
            Cette page assume un theme clair, et un seul.

            La feuille de style de Swagger UI est ecrite en clair et n'expose
            aucune variable : sous un theme sombre, on obtient du gris fonce sur
            des blocs blancs, et la moitie de la page devient illisible. Plutot
            que de repeindre les quelques centaines de regles du paquet, la
            documentation garde une apparence unique, la sienne.
        */
        :root, .swagger-ui { color-scheme: light; }

        :root {
            --orange: #ff8200;
            --orange-dark: #ef6c00;
            --orange-soft: #fff3e6;
            --bg: #fdfaf6;
            --surface: #ffffff;
            --border: #ece7e0;
            --border-strong: #d8d0c6;
            --text: #1a1a1a;
            --muted: #5b6270;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font: 15px/1.6 ui-sans-serif, system-ui, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        /* --- En-tete --------------------------------------------------------- */

        .masthead { border-bottom: 1px solid var(--border); background: var(--surface); }
        .masthead .inner { max-width: 1180px; margin: 0 auto; padding: 42px 28px 28px; }

        .eyebrow {
            margin: 0;
            color: var(--orange-dark);
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .masthead h1 { margin: .5rem 0 0; font-size: 32px; letter-spacing: -.02em; }

        .rule {
            display: block;
            width: 4rem;
            height: 3px;
            margin: .85rem 0 1.1rem;
            border-radius: 999px;
            background: linear-gradient(135deg, #ff9a3c, var(--orange-dark));
        }

        .lead { max-width: 68ch; margin: 0; color: var(--muted); }

        .facts { display: flex; flex-wrap: wrap; gap: 8px; margin: 22px 0 0; padding: 0; list-style: none; }
        .facts li {
            padding: 5px 12px;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: var(--bg);
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
        }
        .facts li code { padding: 0; background: none; font-size: 12px; }

        .actions { margin-top: 24px; display: flex; flex-wrap: wrap; gap: 10px; }
        .actions a {
            padding: 8px 14px;
            border: 1px solid var(--border-strong);
            border-radius: 2px;
            background: var(--surface);
            color: var(--text);
            font-size: .85rem;
            font-weight: 600;
            text-decoration: none;
            transition: border-color .15s, color .15s;
        }
        .actions a:hover { border-color: var(--orange); color: var(--orange-dark); }

        /* --- Swagger UI ------------------------------------------------------ */

        #swagger-ui { max-width: 1180px; margin: 0 auto; padding: 8px 14px 72px; }
        .swagger-ui { font-family: inherit; color: var(--text); }

        /* La banniere du paquet et le titre repete par le bloc « info » disent
           ce que l'en-tete ci-dessus dit deja. Le corps de la description, lui,
           est le mode d'emploi du contrat : il reste. */
        .swagger-ui .topbar,
        .swagger-ui .info hgroup.main,
        .swagger-ui .scheme-container .schemes-title { display: none; }

        .swagger-ui .information-container { padding: 8px 0 0; }
        .swagger-ui .info { margin: 24px 0 0; }
        .swagger-ui .info .description {
            padding: 8px 26px 22px;
            border: 1px solid var(--border);
            border-radius: 2px;
            background: var(--surface);
        }
        .swagger-ui .info .description h2 { margin: 26px 0 8px; font-size: 15px; font-weight: 650; color: var(--text); }
        .swagger-ui .info .description p,
        .swagger-ui .info .description li { color: var(--muted); font-size: 14px; }
        .swagger-ui .info .description strong { color: var(--text); }
        .swagger-ui .info .description code {
            padding: 2px 6px;
            border-radius: 2px;
            background: var(--orange-soft);
            color: var(--orange-dark);
            font-size: 12.5px;
        }

        .swagger-ui .scheme-container { margin: 0; padding: 20px 0 6px; background: transparent; box-shadow: none; }

        .swagger-ui .btn {
            border-radius: 2px;
            border-color: var(--border-strong);
            color: var(--text);
            font-weight: 600;
            box-shadow: none;
            transition: border-color .15s, background-color .15s;
        }
        .swagger-ui .btn:hover { border-color: var(--orange); }
        .swagger-ui .btn.authorize { color: var(--orange-dark); border-color: var(--orange); }
        .swagger-ui .btn.authorize svg { fill: var(--orange-dark); }
        .swagger-ui .btn.execute { background: var(--orange); border-color: var(--orange); color: #fff; }
        .swagger-ui .btn.execute:hover { background: var(--orange-dark); border-color: var(--orange-dark); }

        /* Un tag est un intertitre, pas une banniere. */
        .swagger-ui .opblock-tag {
            margin: 6px 0;
            padding: 26px 0 12px;
            border-bottom: 1px solid var(--border);
            color: var(--text);
            font-size: 17px;
            font-weight: 650;
            letter-spacing: -.01em;
        }
        .swagger-ui .opblock-tag:hover { background: transparent; }
        .swagger-ui .opblock-tag small { color: var(--muted); font-size: 13px; font-weight: 400; }

        /* Une operation = une carte sobre. La couleur du verbe se lit sur la
           pastille et sur le lisere gauche ; le reste reste neutre, sinon la
           page clignote des qu'on deplie trois endpoints. */
        .swagger-ui .opblock {
            margin: 0 0 10px;
            border: 1px solid var(--border);
            border-radius: 2px;
            background: var(--surface);
            box-shadow: none;
        }
        .swagger-ui .opblock.is-open { border-color: var(--border-strong); }
        .swagger-ui .opblock .opblock-summary { padding: 6px 10px; border-bottom: none; }
        .swagger-ui .opblock.is-open .opblock-summary { border-bottom: 1px solid var(--border); }

        .swagger-ui .opblock .opblock-summary-method {
            min-width: 76px;
            padding: 7px 0;
            border-radius: 2px;
            font-size: 12px;
            font-weight: 700;
            text-shadow: none;
            box-shadow: none;
        }
        .swagger-ui .opblock .opblock-summary-path,
        .swagger-ui .opblock .opblock-summary-path__deprecated {
            font-family: ui-monospace, "Cascadia Code", Menlo, Consolas, monospace;
            font-size: 13.5px;
            color: var(--text);
        }
        .swagger-ui .opblock .opblock-summary-description { color: var(--muted); font-size: 13px; }

        .swagger-ui .opblock.opblock-get { border-left: 3px solid #1d78c9; }
        .swagger-ui .opblock.opblock-post { border-left: 3px solid #0f7b56; }
        .swagger-ui .opblock.opblock-patch { border-left: 3px solid var(--orange); }
        .swagger-ui .opblock.opblock-put { border-left: 3px solid #b45309; }
        .swagger-ui .opblock.opblock-delete { border-left: 3px solid #b42318; }

        .swagger-ui .opblock-body,
        .swagger-ui .opblock .opblock-section-header { background: var(--surface); box-shadow: none; }
        .swagger-ui .opblock .opblock-section-header {
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .swagger-ui table thead tr th,
        .swagger-ui table thead tr td {
            border-bottom: 1px solid var(--border);
            color: var(--muted);
            font-size: 11.5px;
            letter-spacing: .05em;
            text-transform: uppercase;
        }
        .swagger-ui .parameter__name { font-weight: 600; }
        .swagger-ui .parameter__type, .swagger-ui .prop-format { color: var(--muted); }

        .swagger-ui select,
        .swagger-ui input[type=text],
        .swagger-ui textarea {
            border: 1px solid var(--border-strong);
            border-radius: 2px;
            background: var(--surface);
            color: var(--text);
            box-shadow: none;
        }
        .swagger-ui select:focus,
        .swagger-ui input[type=text]:focus,
        .swagger-ui textarea:focus { outline: 3px solid var(--orange-soft); border-color: var(--orange); }

        .swagger-ui .highlight-code > .microlight, .swagger-ui .model-box { border-radius: 2px; }
        .swagger-ui .response-col_status { font-weight: 600; }

        @media (max-width: 640px) {
            .masthead .inner { padding: 30px 18px 24px; }
            .masthead h1 { font-size: 26px; }
            #swagger-ui { padding: 4px 8px 56px; }
        }
    </style>
</head>
<body>
    <header class="masthead">
        <div class="inner">
            <p class="eyebrow">Billetterie interurbaine &middot; Reference de l'API</p>
            <h1>Documentation API</h1>
            <span class="rule"></span>
            <p class="lead">
                Contrat complet de l'API REST : endpoints, parametres, corps de
                requete, authentification, codes HTTP et forme des erreurs. Un test
                automatise verifie que cette specification couvre bien toutes les
                routes reellement exposees — la coherence est donc verifiee, pas
                esperee.
            </p>

            <ul class="facts">
                <li>Jeton porteur, expiration bornee</li>
                <li>Identifiants au format UUID</li>
                <li>Erreurs normalisees : <code>error_code</code></li>
                <li>Montants entiers en XOF</li>
            </ul>

            <div class="actions">
                <a href="{{ url('/') }}">&larr; Accueil</a>
                <a href="{{ url('/docs/openapi.json') }}">Telecharger la specification</a>
            </div>
        </div>
    </header>

    <div id="swagger-ui"></div>

    <script src="{{ asset('vendor/swagger-ui/swagger-ui-bundle.js') }}"></script>
    <script>
        window.addEventListener('load', function () {
            window.ui = SwaggerUIBundle({
                url: @json(url('/docs/openapi.json')),
                dom_id: '#swagger-ui',
                deepLinking: true,
                docExpansion: 'list',
                defaultModelsExpandDepth: 0,
                displayRequestDuration: true,
                tryItOutEnabled: true,
                persistAuthorization: true,
                syntaxHighlight: { theme: 'idea' },
            });
        });
    </script>
</body>
</html>
