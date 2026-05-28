<?php

namespace LaraFleet\Agent\Collectors\Contracts;

interface Collector
{
    /**
     * Sammelt Daten und gibt sie als Array zurück.
     * Wirft keine Exceptions — gibt im Fehlerfall leeres Array / null-Werte zurück.
     *
     * @return array<string, mixed>
     */
    public function collect(): array;
}
