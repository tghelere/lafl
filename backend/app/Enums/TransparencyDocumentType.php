<?php

declare(strict_types=1);

namespace App\Enums;

enum TransparencyDocumentType: string
{
    case Balance = 'balance';
    case Bylaws = 'bylaws';
    case Minutes = 'minutes';
    case Certificate = 'certificate';
    case AgreementAccounting = 'agreement_accounting';
    case Notice = 'notice';
    case AnnualReport = 'annual_report';

    public function label(): string
    {
        return match ($this) {
            self::Balance => 'Balanço',
            self::Bylaws => 'Estatuto',
            self::Minutes => 'Ata',
            self::Certificate => 'Certidão',
            self::AgreementAccounting => 'Prestação de contas do convênio',
            self::Notice => 'Edital',
            self::AnnualReport => 'Relatório anual',
        };
    }
}
