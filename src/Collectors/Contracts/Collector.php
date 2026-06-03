<?php

namespace LaraFleet\Agent\Collectors\Contracts;

interface Collector
{
    /**
     * Sammelt Daten und gibt sie als Array zurück.
     * Wirft keine Exceptions — gibt im Fehlerfall null-Werte zurück.
     *
     * @return array<string, mixed>
     */
    public function collect(): array;

    /**
     * Gibt die Top-Level-Keys zurück, die dieser Collector liefert.
     * Wird im Fehlerfall als null-Fallback verwendet.
     *
     * @return list<string>
     */
    public function keys(): array;
}
