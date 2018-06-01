<?php

namespace Krlove\EloquentModelGenerator;

use Krlove\EloquentModelGenerator\Exception\GeneratorException;
use Krlove\CodeGenerator\Model\ClassModel;

/**
 * Class Generator
 * @package Krlove\Generator
 */
class Generator
{
    /**
     * @var EloquentModelBuilder
     */
    protected $builder;

    /**
     * @var TypeRegistry
     */
    protected $typeRegistry;

    /**
     * Generator constructor.
     * @param EloquentModelBuilder $builder
     * @param TypeRegistry $typeRegistry
     */
    public function __construct(EloquentModelBuilder $builder, TypeRegistry $typeRegistry)
    {
        $this->builder = $builder;
        $this->typeRegistry = $typeRegistry;
    }

    /**
     * Generate model that will be used to generate the eloquent class - added ability to check for duplicate methods
     * for cases where same table is referenced more than once
     * @param Config $config
     * @return ClassModel
     * @throws GeneratorException
     */
    public function generateModel(Config $config)
    {
        $this->registerUserTypes($config);

        $model = $this->builder->createModel($config);

        //recheck if the methods are unique
        $existing_methods = $model->getMethods();

        if(!empty($existing_methods) && count($existing_methods) > 1 )
        {
            $unique_method_names = array();

            foreach($existing_methods as $index => $method)
            {
            $unique_method_names[$index] = $method->getName();
            }

            $updated_existing_method = array();
            $unique_method_names = array();

             //we have a problem here
            foreach($existing_methods as $index => $method)
            {
                $current_name = $method->getName();
                if(!in_array($current_name, $unique_method_names))
                {
                    $updated_existing_method[] = $method;
                }
                else
                {
                    //resolve the problem
                    $current_name = $method->getName().$index; //resolve it by adding the index as a postfix
                    $method->setName( $current_name );
                    $updated_existing_method[] = $method;
                }

                $unique_method_names[] = $current_name;
            }

            if(!empty($updated_existing_method))
            {
                //reset the methods
                $model->resetMethods();
                //add the methods
                foreach($updated_existing_method as $method)
                {
                    $model->addMethod($method);
                }
                
            }
        }

        $content = $model->render();

        $outputPath = $this->resolveOutputPath($config);
        file_put_contents($outputPath, $content);

        return $model;
    }

    /**
     * @param Config $config
     * @return string
     * @throws GeneratorException
     */
    protected function resolveOutputPath(Config $config)
    {
        $path = $config->get('output_path');
        if ($path === null || stripos($path, '/') !== 0) {
            $path = app_path($path);
        }

        if (!is_dir($path)) {
            if (!mkdir($path, 0777, true)) {
                throw new GeneratorException(sprintf('Could not create directory %s', $path));
            }
        }

        if (!is_writeable($path)) {
            throw new GeneratorException(sprintf('%s is not writeable', $path));
        }

        return $path . '/' . $config->get('class_name') . '.php';
    }

    /**
     * @param Config $config
     */
    protected function registerUserTypes(Config $config)
    {
        $userTypes = $config->get('db_types');
        if ($userTypes && is_array($userTypes)) {
            $connection = $config->get('connection');

            foreach ($userTypes as $type => $value) {
                $this->typeRegistry->registerType($type, $value, $connection);
            }
        }
    }
}
