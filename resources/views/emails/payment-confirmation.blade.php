<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de votre commande DOCSFLOW</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f7fb; color:#122033; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f7fb; margin:0; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px; background-color:#ffffff; border-radius:20px; overflow:hidden; box-shadow:0 12px 40px rgba(18, 32, 51, 0.10);">
                    <tr>
                        <td style="padding:32px 36px; background:linear-gradient(135deg, #0f766e 0%, #0b3b5b 100%); color:#ffffff;">
                            <div style="font-size:12px; letter-spacing:0.16em; text-transform:uppercase; opacity:0.82;">DOCSFLOW</div>
                            <h1 style="margin:12px 0 0; font-size:28px; line-height:1.2; font-weight:700;">Votre commande a bien été confirmée</h1>
                            <p style="margin:14px 0 0; font-size:15px; line-height:1.7; color:rgba(255,255,255,0.9);">
                                @if ($customerName !== 'Bonjour')
                                    Merci {{ $customerName }}. Votre paiement a bien été enregistré et votre accès au service DOCSFLOW est désormais actif.
                                @else
                                    Votre paiement a bien été enregistré et votre accès au service DOCSFLOW est désormais actif.
                                @endif
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px 36px 12px;">
                            <p style="margin:0 0 18px; font-size:15px; line-height:1.7; color:#334155;">
                                Vous trouverez ci-dessous le récapitulatif de votre commande ainsi que les principales informations liées à votre période d’essai.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #dbe5ef; border-radius:16px; overflow:hidden; background-color:#f8fbfd;">
                                <tr>
                                    <td style="padding:18px 20px; border-bottom:1px solid #dbe5ef;">
                                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.08em; color:#64748b;">Référence</div>
                                        <div style="margin-top:6px; font-size:16px; font-weight:700; color:#0f172a;">{{ $orderReference }}</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:18px 20px; border-bottom:1px solid #dbe5ef;">
                                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.08em; color:#64748b;">Montant réglé</div>
                                        <div style="margin-top:6px; font-size:16px; font-weight:700; color:#0f172a;">{{ $amount }} {{ $currency }}</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:18px 20px; border-bottom:1px solid #dbe5ef;">
                                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.08em; color:#64748b;">Entreprise recherchée</div>
                                        <div style="margin-top:6px; font-size:16px; font-weight:700; color:#0f172a;">
                                            {{ $identifier }}
                                            @if ($companyName)
                                                <span style="display:block; margin-top:4px; font-size:14px; font-weight:400; color:#475569;">{{ $companyName }}</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.08em; color:#64748b;">Période d’essai</div>
                                        <div style="margin-top:6px; font-size:16px; font-weight:700; color:#0f172a;">{{ $trialHours }} heures</div>
                                        @if ($trialEnd)
                                            <div style="margin-top:6px; font-size:14px; color:#475569;">Fin estimée le {{ $trialEnd }}</div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:12px 36px 0;">
                            <div style="padding:18px 20px; border-radius:16px; background-color:#fff7ed; border:1px solid #fed7aa;">
                                <div style="font-size:14px; font-weight:700; color:#9a3412;">Information importante</div>
                                <p style="margin:8px 0 0; font-size:14px; line-height:1.7; color:#7c2d12;">
                                    Sauf résiliation avant l’échéance de l’essai, l’abonnement mensuel se poursuivra ensuite selon les conditions en vigueur, au tarif de {{ $monthlyAmount }} EUR par mois.
                                </p>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 36px 36px;">
                            <p style="margin:0; font-size:15px; line-height:1.8; color:#334155;">
                                Si vous avez besoin d’aide ou souhaitez nous contacter, notre équipe reste disponible à l’adresse
                                <a href="mailto:{{ $supportEmail }}" style="color:#0f766e; text-decoration:none; font-weight:700;">{{ $supportEmail }}</a>.
                            </p>
                            <p style="margin:22px 0 0; font-size:15px; line-height:1.8; color:#334155;">
                                Bien cordialement,<br>
                                <span style="font-weight:700; color:#0f172a;">L’équipe DOCSFLOW</span>
                            </p>
                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;">
                    <tr>
                        <td style="padding:18px 12px 0; text-align:center; font-size:12px; line-height:1.7; color:#64748b;">
                            Cet email confirme votre commande sur docsflow.fr.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
