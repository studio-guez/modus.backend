<?php

require_once dirname(__DIR__, 2) . '/_utils/SpamGuard.php';

return function ($kirby, $pages, $page) {

    $alert   = null;
    $data    = [];
    $success = false;

    if ($kirby->request()->is('POST')) {

        // ── 1. Honeypot ─────────────────────────────────────────────────────
        // Hidden from real users. Only bots that blindly fill every field
        // will populate it. Return a fake success so the bot does not adapt.
        if (trim((string) get('website', '')) !== '') {
            return [
                'alert'   => null,
                'data'    => false,
                'success' => 'Votre message a bien été envoyé. Nous revenons vers vous au plus vite.',
            ];
        }

        // ── 2. Rate limiting ────────────────────────────────────────────────
        // Max 10 submissions per IP per 15 minutes. Requires the 'contact-form'
        // cache to be enabled in config.php. If it is not configured the
        // exception is caught and rate limiting is skipped silently so that
        // a misconfiguration never breaks the form for real users.
        try {
            $rateCache = $kirby->cache('contact-form');
            $ipHash    = hash('sha256', (string) $kirby->visitor()->ip());
            $rateKey   = 'attempts-' . $ipHash;
            $attempts  = (int) $rateCache->get($rateKey, 0);

            if ($attempts >= 10) {
                return [
                    'alert'   => null,
                    'data'    => false,
                    'success' => 'Votre message a bien été envoyé. Nous revenons vers vous au plus vite.',
                ];
            }

            $rateCache->set($rateKey, $attempts + 1, 15);
        } catch (Exception $e) {
            // Cache not configured; skip rate limiting gracefully.
        }

        // ── 3. Collect input ────────────────────────────────────────────────
        // Keep raw values for spam analysis: strip_tags() would remove
        // executable tags before SpamGuard can detect them.
        // Use the stripped values for validation and the outgoing email.
        $rawData = [
            'nom'         => trim((string) get('nom', '')),
            'prenom'      => trim((string) get('prenom', '')),
            'institution' => trim((string) get('institution', '')),
            'email'       => trim((string) get('email', '')),
            'nomProjet'   => trim((string) get('nomProjet', '')),
            'description' => trim((string) get('description', '')),
        ];

        $data = array_map(
            static fn(string $v): string => trim(strip_tags($v)),
            $rawData
        );

        // ── 4. Server-side validation ────────────────────────────────────────
        $rules = [
            'nom'         => ['required', 'minLength' => 2, 'maxLength' => 60],
            'prenom'      => ['required', 'minLength' => 2, 'maxLength' => 60],
            'institution' => ['required', 'minLength' => 2, 'maxLength' => 150],
            'email'       => ['required', 'email', 'maxLength' => 190],
            'nomProjet'   => ['required', 'minLength' => 3, 'maxLength' => 200],
            'description' => ['required', 'minLength' => 10, 'maxLength' => 1250],
        ];

        $messages = [
            'nom'         => 'Merci de renseigner un nom valide',
            'prenom'      => 'Merci de renseigner un prénom valide',
            'institution' => 'Merci de renseigner une institution valide',
            'email'       => 'Entrez une adresse mail valide',
            'nomProjet'   => 'Merci de renseigner un titre de projet valide',
            'description' => 'Merci de renseigner une description valide (10 caractères minimum)',
        ];

        if ($invalid = invalid($data, $rules, $messages)) {
            $alert = $invalid;
        } else {

            // ── 5. Spam scoring ─────────────────────────────────────────────
            // Checks run on raw input so executable tags are still visible.
            //   total ≥ 4 → silently discard
            //   total < 4 → deliver normally
            $spamScore =
                SpamGuard::scoreField($rawData['nom'],         SpamGuard::FIELD_NAME)
                + SpamGuard::scoreField($rawData['prenom'],      SpamGuard::FIELD_NAME)
                + SpamGuard::scoreField($rawData['institution'], SpamGuard::FIELD_SHORT)
                + SpamGuard::scoreField($rawData['nomProjet'],   SpamGuard::FIELD_SHORT)
                + SpamGuard::scoreField($rawData['description'], SpamGuard::FIELD_TEXT);

            if ($spamScore >= 4) {
                return [
                    'alert'   => null,
                    'data'    => false,
                    'success' => 'Votre message a bien été envoyé. Nous revenons vers vous au plus vite.',
                ];
            }

            // ── 6. Send email ────────────────────────────────────────────────
            try {
                $nom         = $data['nom'];
                $prenom      = $data['prenom'];
                $institution = $data['institution'];
                $email       = $data['email'];
                $nomProjet   = $data['nomProjet'];
                $description = $data['description'];

                $emailFrom   = option('emailFrom', []);
                $fromAddress = $emailFrom['address'] ?? 'webmaster@cms.modus-ge.ch';
                $fromName    = $emailFrom['name'] ?? 'Modus';

                // Strip newlines from values used in the subject line to
                // prevent email header injection.
                $safeNom    = (string) preg_replace('/[\r\n]+/', ' ', $nom);
                $safePrenom = (string) preg_replace('/[\r\n]+/', ' ', $prenom);

                $kirby->email([
                    'from'    => [$fromAddress => $fromName],
                    'to'      => ['info@modus-ge.ch'],
                    'replyTo' => $email,
                    'subject' => 'contact modus-ge.ch | '
                        . $safeNom . ' ' . $safePrenom
                        . " vous a envoyé un message depuis l'application web modus-ge.ch",
                    'body'    =>
                    "Nouvelle prise de contact de $nom $prenom:"
                        . "\n\nNOM\n$nom"
                        . "\n\nPRÉNOM\n$prenom"
                        . "\n\nINSTITUTION\n$institution"
                        . "\n\nEMAIL\n$email"
                        . "\n\nTITRE DU PROJET\n$nomProjet"
                        . "\n\nDESCRIPTION\n$description",
                ]);
            } catch (Exception $error) {
                $alert['error'] = 'The form could not be sent: ' . $error->getMessage();
            }

            if (empty($alert) === true) {
                $success = 'Votre message a bien été envoyé. Nous revenons vers vous au plus vite.';
            }
        }
    }

    return [
        'alert'   => $alert,
        'data'    => $data ?? false,
        'success' => $success ?? false,
    ];
};
