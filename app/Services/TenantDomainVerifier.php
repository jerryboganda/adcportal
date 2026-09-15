<?php

namespace App\Services;

use App\Models\TenantDomain;

/**
 * DNS-based ownership verification for a custom domain (master-prompt §38).
 *
 * A host is only allowed to serve tenant branding once the operator has proven
 * control of it by publishing a TXT record:
 *
 *     _polytronx-ris.<host>   TXT   "polytronx-ris-verify=<TENANT_CODE>"
 *
 * The lookup is injectable so the verification logic can be exercised
 * deterministically in tests without depending on live DNS.
 */
class TenantDomainVerifier
{
    public const RECORD_PREFIX = '_polytronx-ris';

    /** @var null|\Closure(string): array<int, string> */
    private ?\Closure $lookup;

    public function __construct(?\Closure $lookup = null)
    {
        $this->lookup = $lookup;
    }

    /** @return array{verified: bool, expected: string, found: array<int, string>} */
    public function verify(TenantDomain $domain): array
    {
        $expected = $this->expectedValue($domain);
        $found = $this->txtRecords(self::RECORD_PREFIX.'.'.$domain->host);

        return [
            'verified' => in_array($expected, $found, true),
            'expected' => $expected,
            'found' => $found,
        ];
    }

    public function expectedValue(TenantDomain $domain): string
    {
        $code = $domain->business?->tenant_code ?? '';

        return 'polytronx-ris-verify='.$code;
    }

    /** @return array<int, string> */
    private function txtRecords(string $name): array
    {
        if ($this->lookup) {
            return array_values((array) ($this->lookup)($name));
        }

        $records = @dns_get_record($name, DNS_TXT) ?: [];

        return array_values(array_filter(array_map(
            fn ($r) => $r['txt'] ?? ($r['entries'][0] ?? null),
            $records
        )));
    }
}
