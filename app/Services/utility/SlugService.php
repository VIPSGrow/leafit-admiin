<?php
namespace App\Services\utility;

class SlugService
{
    protected $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function resolve(
        ?string $currentSlug,
        ?string $inputSlug,
        string $fallbackName,
        string $table,
        ?int $excludeId = null
    ): string {
        if (!empty($currentSlug) && empty($inputSlug)) {
            return $currentSlug;
        }

        if (!empty($inputSlug)) {
            return $this->generate($inputSlug, $fallbackName, $table, $excludeId);
        }

        return $this->generate($fallbackName, $fallbackName, $table, $excludeId);
    }

    public function generate(
        string $raw,
        string $fallback,
        string $table,
        ?int $excludeId = null
    ): string {
        $base = $this->normalize($raw ?: $fallback);

        if ($base === '') {
            $base = 'item';
        }

        if (!$this->exists($base, $table, $excludeId)) {
            return $base;
        }

        return $base . '-' . $this->shortHash($base, $excludeId);
    }

    protected function normalize(string $value): string
    {
        $value = trim($value);

        if (class_exists(\Transliterator::class)) {
            $trans = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
            $value = $trans ? $trans->transliterate($value) : $value;
        }

        $value = preg_replace('/[^a-z0-9\s-]/', '', $value);
        $value = preg_replace('/[\s-]+/', '-', $value);

        return trim($value, '-');
    }

    protected function shortHash(string $value, ?int $salt = null): string
    {
        return substr(
            hash('sha256', $value . '|' . ($salt ?? 'static')),
            0,
            6
        );
    }

    protected function exists(string $slug, string $table, ?int $excludeId): bool
    {
        $qb = $this->db->table($table)->where('slug', $slug);

        if ($excludeId !== null) {
            $qb->where('id !=', $excludeId);
        }

        return $qb->countAllResults() > 0;
    }

    public function isLegacySlug(string $slug): bool
    {
        return (bool) preg_match('/(-\d+)+$/', $slug);
    }
}