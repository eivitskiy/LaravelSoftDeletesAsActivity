<?php

namespace Eivitskiy\LaravelSoftDeletesAsActivity;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

/**
 * @mixin Model
 */
trait SoftDeletesAsActivity
{
    protected bool $forceDeleting = false;

    /** @noinspection PhpUnused */
    public static function bootSoftDeletesAsActivity(): void
    {
        static::addGlobalScope(new SoftDeletingAsActivityScope);
    }

    /** @noinspection PhpUnused */
    public function initializeSoftDeletes(): void
    {
        if (! isset($this->casts[$this->getIsActiveColumn()])) {
            $this->casts[$this->getIsActiveColumn()] = 'bool';
        }
    }

    public function forceDelete(): ?bool
    {
        $this->forceDeleting = true;

        return tap($this->delete(), function ($deleted) {
            $this->forceDeleting = false;

            if ($deleted) {
                $this->fireModelEvent('forceDeleted', false);
            }
        });
    }

    /** @noinspection PhpUnused */
    protected function performDeleteOnModel(): mixed
    {
        if ($this->forceDeleting) {
            return tap($this->setKeysForSaveQuery($this->newModelQuery())->forceDelete(), function () {
                $this->exists = false;
            });
        }

        /** @noinspection PhpVoidFunctionResultUsedInspection */
        return $this->runSoftDelete();
    }

    protected function runSoftDelete(): void
    {
        $query = $this->setKeysForSaveQuery($this->newModelQuery());

        $time = $this->freshTimestamp();

        $columns = [$this->getIsActiveColumn() => false];

        $this->{$this->getIsActiveColumn()} = false;

        if ($this->timestamps && ! is_null($this->getUpdatedAtColumn())) {
            $this->{$this->getUpdatedAtColumn()} = $time;

            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
        }

        $query->update($columns);

        $this->syncOriginalAttributes(array_keys($columns));

        $this->fireModelEvent('trashed', false);
    }

    public function restore(): ?bool
    {
        if (false === $this->fireModelEvent('restoring')) {
            return false;
        }

        $this->{$this->getIsActiveColumn()} = true;

        $this->exists = true;

        $result = $this->save();

        $this->fireModelEvent('restored', false);

        return $result;
    }

    /** @noinspection PhpUnused */
    public function restoreQuietly(): ?bool
    {
        return static::withoutEvents(fn () => $this->restore());
    }

    /** @noinspection PhpUnused */
    public function trashed(): bool
    {
        return ! $this->{$this->getIsActiveColumn()};
    }

    /** @noinspection PhpUnused */
    public static function softDeleted(string|Closure $callback): void
    {
        static::registerModelEvent('trashed', $callback);
    }

    public static function restoring(string|Closure $callback): void
    {
        static::registerModelEvent('restoring', $callback);
    }

    /** @noinspection PhpUnused */
    public static function restored(string|Closure $callback): void
    {
        static::registerModelEvent('restored', $callback);
    }

    /** @noinspection PhpUnused */
    public static function forceDeleted(string|Closure $callback): void
    {
        static::registerModelEvent('forceDeleted', $callback);
    }

    /** @noinspection PhpUnused */
    public function isForceDeleting(): bool
    {
        return $this->forceDeleting;
    }

    public function getIsActiveColumn(): string
    {
        return defined(static::class.'::IS_ACTIVE')
            ? static::IS_ACTIVE
            : 'is_active';
    }

    /** @noinspection PhpUnused */
    public function getQualifiedIsActiveColumn(): string
    {
        return $this->qualifyColumn($this->getIsActiveColumn());
    }
}