<?php

declare(strict_types=1);

namespace App\Utilities;

class ShippingCountries
{
    public static function allowed(): array
    {
        return [
            'CA', 'MX', 'US', 'AG', 'AI', 'AW', 'BB', 'BL', 'BM', 'BQ', 'BS', 'BZ',
            'CR', 'CW', 'DM', 'DO', 'GD', 'GL', 'GP', 'GT', 'HN', 'HT', 'JM', 'KN',
            'KY', 'LC', 'MF', 'MQ', 'MS', 'NI', 'PA', 'PM', 'PR', 'SV', 'SX', 'TC',
            'TT', 'VC', 'VG', 'AR', 'BO', 'BR', 'BV', 'CL', 'CO', 'EC', 'FK', 'GF',
            'GS', 'GY', 'PE', 'PY', 'SR', 'UY', 'VE', 'AD', 'AL', 'AT', 'AX', 'BA',
            'BE', 'BG', 'BY', 'CH', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FO', 'FR',
            'GB', 'GG', 'GI', 'GR', 'HR', 'HU', 'IE', 'IM', 'IS', 'IT', 'JE', 'LI',
            'LT', 'LU', 'LV', 'MC', 'MD', 'ME', 'MK', 'MT', 'NL', 'NO', 'PL', 'PT',
            'RO', 'RS', 'RU', 'SE', 'SI', 'SJ', 'SK', 'SM', 'UA', 'VA', 'AO', 'BF',
            'BI', 'BJ', 'BW', 'CD', 'CF', 'CG', 'CI', 'CM', 'CV', 'DJ', 'DZ', 'EG',
            'ER', 'ET', 'GA', 'GH', 'GM', 'GN', 'GQ', 'GW', 'IO', 'KE', 'KM', 'LR',
            'LS', 'LY', 'MA', 'MG', 'ML', 'MR', 'MU', 'MW', 'MZ', 'NA', 'NE', 'NG',
            'RE', 'RW', 'SC', 'SH', 'SL', 'SN', 'SO', 'SS', 'ST', 'SZ', 'TD', 'TF',
            'TG', 'TN', 'TZ', 'UG', 'YT', 'ZA', 'ZM', 'ZW',
        ];
    }
}
