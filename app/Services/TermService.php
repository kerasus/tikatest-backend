<?php

namespace App\Services;

use App\Models\AcademicTerm;
use Illuminate\Database\Eloquent\Collection;

class TermService
{
    public function create(array $data): AcademicTerm
    {
        $schoolId = $data['school_id'];

        if (! empty($data['is_active']) && $data['is_active']) {
            $this->deactivateActiveTerm($schoolId);

            if (! empty($data['parent_id'])) {
                $parent = AcademicTerm::find($data['parent_id']);
                if ($parent && $parent->is_active) {
                    $parent->update(['is_active' => false]);
                }
            }
        }

        return AcademicTerm::create($data);
    }

    public function update(AcademicTerm $term, array $data): AcademicTerm
    {
        $isActivating = isset($data['is_active']) && $data['is_active'] && ! $term->is_active;
        $isDeactivating = isset($data['is_active']) && ! $data['is_active'] && $term->is_active;

        if ($isActivating) {
            $this->deactivateActiveTerm($term->school_id, $term->id);

            if ($term->parent_id) {
                $parent = AcademicTerm::find($term->parent_id);
                if ($parent && $parent->is_active) {
                    $parent->update(['is_active' => false]);
                }
            }
        }

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

    private function deactivateActiveTerm(int $schoolId, ?int $excludeId = null): void
    {
        $query = AcademicTerm::where('school_id', $schoolId)
            ->where('is_active', true);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $query->update(['is_active' => false]);
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
