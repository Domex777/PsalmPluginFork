<?php

namespace Psalm\LaravelPlugin\Fakes;

use Barryvdh\LaravelIdeHelper\Console\ModelsCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Psalm\LaravelPlugin\Handlers\Eloquent\Schema\SchemaAggregator;
use Psalm\LaravelPlugin\Handlers\Eloquent\Schema\SchemaColumn;

use function config;
use function get_class;
use function in_array;
use function implode;
use function method_exists;
use function sprintf;

/** @psalm-suppress PropertyNotSetInConstructor */
class FakeModelsCommand extends ModelsCommand
{
    /** @var list<class-string<\Illuminate\Database\Eloquent\Model>> */
    private $model_classes = [];

    /** @var SchemaAggregator */
    private $schema;

    public function __construct(Filesystem $files, SchemaAggregator $schema)
    {
        parent::__construct($files);
        $this->schema = $schema;
    }

    /** @return list<class-string<\Illuminate\Database\Eloquent\Model>> */
    public function getModels(): array
    {
        return $this->model_classes + $this->loadModels();
    }

    /**
     * Load Model's properties.
     * Overrides {@see \Barryvdh\LaravelIdeHelper\Console\ModelsCommand::getPropertiesFromTable}
     * in order to avoid using DB connection and use SchemaAggregator instead.
     *
     * @param Model $model
     */
    public function getPropertiesFromTable($model): void
    {
        $is_parent_method_still_exist = method_exists(ModelsCommand::class, __METHOD__);
        if (! $is_parent_method_still_exist) {
            throw new \BadMethodCallException(sprintf(
                'Method %s::%s() does not exist anymore. Please rename overridden method accordingly.',
                ModelsCommand::class,
                __METHOD__
            ));
        }

        $table_name = $model->getTable();

        if (!isset($this->schema->tables[$table_name])) {
            return;
        }

        $this->model_classes[] = get_class($model);

        $columns = $this->schema->tables[$table_name]->columns;

        foreach ($columns as $column) {
            $name = $column->name;

            if (in_array($name, $model->getDates())) {
                $get_type = $set_type = '\Illuminate\Support\Carbon';
            } else {
                switch ($column->type) {
                    case SchemaColumn::TYPE_STRING:
                    case SchemaColumn::TYPE_INT:
                    case SchemaColumn::TYPE_FLOAT:
                        $get_type = $set_type = $column->type;
                        break;

                    case SchemaColumn::TYPE_BOOL:
                        switch (config('database.default')) {
                            case 'sqlite':
                            case 'mysql':
                                $set_type = '0|1|bool';
                                $get_type = '0|1';
                                break;
                            default:
                                $get_type = $set_type = 'bool';
                                break;
                        }

                        break;

                    case SchemaColumn::TYPE_ENUM:
                        if (!$column->options) {
                            $get_type = $set_type = 'string';
                        } else {
                            $get_type = $set_type = '\'' . implode('\'|\'', $column->options) . '\'';
                        }

                        break;

                    default:
                        $get_type = $set_type = SchemaColumn::TYPE_MIXED;
                        break;
                }
            }

            if ($column->nullable) {
                $this->nullableColumns[$name] = true;
            }

            if ($get_type === $set_type) {
                $this->setProperty($name, $get_type, true, true, '', $column->nullable);
            } else {
                $this->setProperty($name, $get_type, true, null, '', $column->nullable);
                $this->setProperty($name, $set_type, null, true, '', $column->nullable);
            }

            if ($this->write_model_magic_where) {
                $this->setMethod(
                    Str::camel("where_" . $name),
                    '\Illuminate\Database\Eloquent\Builder|\\' . get_class($model),
                    array('$value')
                );
            }
        }
    }
}
