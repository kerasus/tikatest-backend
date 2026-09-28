<?php

namespace App\Services;

use App\Models\AcademicTerm;
use Illuminate\Database\Eloquent\Collection;

class TermService
{
    public function create(array $data): AcademicTerm
    {
        return AcademicTerm::create($data);
    }

    public function update(AcademicTerm $term, array $data): AcademicTerm
    {
        $isDeactivating = isset($data['is_active']) && ! $data['is_active'] && $term->is_active;

        if ($isDeactivating) {
            $term->children()->where('is_active', true)->update(['is_active' => false]);
        }

        $term->update($data);

        return $term->fresh();
    }

    public function getActiveTermWithParents(int $schoolId): ?AcademicTerm
    {
        $activeTerm = AcademicTerm::where('school_id', $schoolId)
            ->where('is_active', true)
            ->with('parentTerm')
            ->first();

        if (! $activeTerm) {
            return null;
        }

        $parents = $this->getParents($activeTerm);
        $activeTerm->setRelation('parents', $parents);

        return $activeTerm;
    }

    private function getParents(AcademicTerm $term): Collection
    {
        $parents = collect();
        $current = $term->parentTerm;

        while ($current) {
            $parents->prepend($current);
            $current = $current->parentTerm;
        }

        return new Collection($parents->all());
    }
}
