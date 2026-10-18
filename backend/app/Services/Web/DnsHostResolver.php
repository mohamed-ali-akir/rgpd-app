<?php

namespace App\Services\Web;

class DnsHostResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];

        // IPv6 : dns_get_record peut échouer selon le système ; on ne le considère pas comme bloquant.
        $records = @dns_get_record($host, DNS_AAAA) ?: [];
        foreach ($records as $record) {
            if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }
}
