<?php

namespace InsertOnDuplicateKey;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

class MysqlReplaceServiceProvider extends ServiceProvider
{
    /**
     * Register the insert macros.
     */
    public function boot()
    {
        /**
         * Run an insert on duplicate key update statement against the database.
         *
         * @param  array $values
         * @param  array $columnsToUpdate
         * @param  string $type
         * @return bool
         */
        Builder::macro('replace', function (
            array $values,
            array $columnsToUpdate = null,
            $type = 'on duplicate key'
        ) {
            // Since every insert gets treated like a batch insert, we will make sure the
            // bindings are structured in a way that is convenient for building these
            // inserts statements by verifying the elements are actually an array.
            if (empty($values)) {
                return true;
            }

            if (!is_array(reset($values))) {
                $values = [$values];
            }

            // Here, we will sort the insert keys for every record so that each insert is
            // in the same order for the record. We need to make sure this is the case
            // so there are not any errors or problems when inserting these records.
            else {
                foreach ($values as $key => $value) {
                    ksort($value);
                    $values[$key] = $value;
                }
            }

            // Finally, we will run this query against the database connection and return
            // the results. We will need to also flatten these bindings before running
            // the query so they are all in one huge, flattened array for execution.
            $bindings = $this->cleanBindings(Arr::flatten($values, 1));

            // Essentially we will force every insert to be treated as a batch insert which
            // simply makes creating the SQL easier for us since we can utilize the same
            // basic routine regardless of an amount of records given to us to insert.
            $table = $this->grammar->wrapTable($this->from);

            $columns = array_keys(reset($values));

            $columnsString = $this->grammar->columnize($columns);

            // We need to build a list of parameter place-holders of values that are bound
            // to the query. Each insert should have the exact same amount of parameter
            // bindings so we will loop through the record and parameterize them all.
            $parameters = collect($values)->map(function ($record) {
                return '(' . $this->grammar->parameterize($record) . ')';
            })->implode(', ');

            $sql = "replace into $table ($columnsString) values $parameters";

            return $this->connection->insert($sql, $bindings);
        });

        /**
         * Attach models to the parent ignoring existing associations.
         *
         * @param  mixed $id
         * @param  array $attributes
         * @return void
         */
        BelongsToMany::macro('attachReplace', function ($id, array $attributes = [], $touch = true) {
            $this->newPivotStatement()->replace($this->formatAttachRecords(
                $this->parseIds($id), $attributes
            ));

            if ($touch) {
                $this->touchIfTouching();
            }
        });
    }
}
