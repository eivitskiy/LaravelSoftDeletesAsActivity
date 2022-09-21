<?php

declare(strict_types=1);

namespace Eivitskiy\LaravelSoftDeletesAsActivity;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class SoftDeletingAsActivityScope implements Scope
{
    protected array $extensions = ['Restore', 'WithTrashed', 'WithoutTrashed', 'OnlyTrashed'];

    /** @noinspection PhpUndefinedMethodInspection */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->getQualifiedIsActiveColumn(), true);
    }

    public function extend(Builder $builder): void
    {
        foreach ($this->extensions as $extension) {
            $this->{"add$extension"}($builder);
        }

        $builder->onDelete(function (Builder $builder) {
            $column = $this->getIsActiveColumn($builder);

            return $builder->update([
                $column => $builder->getModel()->freshTimestampString(),
            ]);
        });
    }

    /** @noinspection PhpUndefinedMethodInspection */
    protected function getIsActiveColumn(Builder $builder): string
    {
        if (count($builder->getQuery()->joins) > 0) {
            return $builder->getModel()->getQualifiedIsActiveColumn();
        }

        return $builder->getModel()->getIsActiveColumn();
    }

    /**
     * @noinspection PhpUndefinedMethodInspection
     * @noinspection PhpUnused
     */
    protected function addRestore(Builder $builder): void
    {
        $builder->macro('restore', function (Builder $builder) {
            $builder->withTrashed();

            return $builder->update([$builder->getModel()->getIsActiveColumn() => null]);
        });
    }

    /**
     * @noinspection PhpUndefinedMethodInspection
     * @noinspection PhpUnused
     */
    protected function addWithTrashed(Builder $builder): void
    {
        $builder->macro('withTrashed', function (Builder $builder, $withTrashed = true) {
            if (!$withTrashed) {
                return $builder->withoutTrashed();
            }

            return $builder->withoutGlobalScope($this);
        });
    }

    /**
     * @noinspection PhpUndefinedMethodInspection
     * @noinspection PhpUnused
     */    protected function addWithoutTrashed(Builder $builder): void
    {
        $builder->macro('withoutTrashed', function (Builder $builder) {
            $model = $builder->getModel();

            $builder->withoutGlobalScope($this)->whereNull(
                $model->getQualifiedIsActiveColumn()
            );

            return $builder;
        });
    }

    /**
     * @noinspection PhpUndefinedMethodInspection
     * @noinspection PhpUnused
     */
    protected function addOnlyTrashed(Builder $builder): void
    {
        $builder->macro('onlyTrashed', function (Builder $builder) {
            $model = $builder->getModel();

            $builder->withoutGlobalScope($this)
                ->where($model->getQualifiedIsActiveColumn(), false);

            return $builder;
        });
    }
}
