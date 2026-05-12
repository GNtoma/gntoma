<?php
declare(strict_types=1);

/**
 * Masque une adresse e-mail pour l’affichage public (partie locale + domaine).
 * Ex. sethmwatha@gmail.com → se....ha@gm....com
 */

function gntoma_mask_email(string $email): string
{
    $email = trim($email);
    if ($email === '') {
        return '';
    }

    $at = strpos($email, '@');
    if ($at === false) {
        return '••••••••';
    }

    $local = substr($email, 0, $at);
    $domainFull = substr($email, $at + 1);
    $len = strlen($local);

    if ($len <= 1) {
        $maskedLocal = $local . '....';
    } elseif ($len <= 4) {
        $maskedLocal = substr($local, 0, 1) . '....' . substr($local, -1);
    } else {
        $maskedLocal = substr($local, 0, 2) . '....' . substr($local, -2);
    }

    if ($domainFull === '') {
        return $maskedLocal;
    }

    $domainFull = strtolower($domainFull);
    $dotPos = strrpos($domainFull, '.');
    if ($dotPos === false) {
        $dlen = strlen($domainFull);

        return $maskedLocal . '@' . ($dlen <= 2 ? $domainFull . '....' : substr($domainFull, 0, 2) . '....');
    }

    $tld = substr($domainFull, $dotPos + 1);
    $hostPart = substr($domainFull, 0, $dotPos);

    if (str_contains($hostPart, '.')) {
        $parts = explode('.', $hostPart);
        $hostPart = end($parts) ?: $hostPart;
    }

    $hl = strlen($hostPart);
    if ($hl <= 2) {
        $maskedDomain = $hostPart . '....' . $tld;
    } else {
        $maskedDomain = substr($hostPart, 0, 2) . '....' . $tld;
    }

    return $maskedLocal . '@' . $maskedDomain;
}
