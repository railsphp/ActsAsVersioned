<?php
namespace Rails\ActsAsVersioned;

trait Versioning
{
    protected $versioningConfig = [];
    
    public function revertTo($version)
    {
        $this->setVersioningConfig();
        
        $primary_key = static::table()->primaryKey();
        
        $query = $this->versioningRelation();
        
        $query->where('`' . $this->versioningConfig['foreign_key'] . '` = ?', $this->$primary_key)
                ->where('`' . $this->versioningConfig['versions_column'] . '` = ?', $version)
                ->from($this->versioningConfig['table_name']);
        
        $oldModel = $query->first();
        
        if (!$oldModel) {
            return false;
        }
        
        $attributes = $this->restoringAttrs($oldModel);
        
        unset($attributes[$this->versioningConfig['foreign_key']], $attributes[$primary_key]);
        
        $this->assignAttributes($attributes);
        
        return true;
    }
    
    protected function versioningCallbacks()
    {
        return [
            'before_save' => ['setNextVersion'],
            'after_save'  => ['versionThis']
        ];
    }
    
    protected function versioningRelation()
    {
        return static::none();
    }
    
    protected function setNextVersion()
    {
        $this->version = $this->nextVersion();
    }
    
    protected function versionThis()
    {
        $this->setVersioningConfig();
        $primary_key = static::table()->primaryKey();
        
        $class_name = $this->versioningConfig['class_name'];
        
        $versioned = new $class_name();
        $versioned->assignAttributes($this->versioningAttrs());
        $versioned->{$this->versioningConfig['foreign_key']} = $this->$primary_key;
        $versioned->{$this->versioningConfig['versions_column']} = $this->version;
        return $versioned->save();
    }
    
    protected function nextVersion()
    {
        $this->setVersioningConfig();
        $primary_key = static::table()->primaryKey();
        $value = (int)static::connection()->selectValue(
            'SELECT `' . $this->versioningConfig['versions_column'] . '` FROM `'.$this->versioningConfig['table_name'].
            '` WHERE `'.$this->versioningConfig['foreign_key'].
            '` = ? ORDER BY `' . $this->versioningConfig['versions_column'] . '` DESC', $this->$primary_key) ?: 0;
        return $value + 1;
    }
    
    protected function actsAsVersionedConfig()
    {
        return [];
    }
    
    protected function versioningAttrs()
    {
        return $this->attributes();
    }
    
    protected function restoringAttrs($oldModel)
    {
        return $oldModel->attributes();
    }
    
    private function setVersioningConfig()
    {
        if (!$this->versioningConfig) {
            $this->versioningConfig = array_merge($this->defaultVersioningConfig(), $this->actsAsVersionedConfig());
        }
    }
    
    private function defaultVersioningConfig()
    {
        $tableName = static::table()->name() . '_versions';
        return [
            /**
             *
             */
            'table_name'  => $tableName,
            
            /**
             * Column in the versions table that makes reference to the
             * versioning table id.
             */
            'foreign_key' => static::table()->name() . '_id',
            
            /**
             * Class name of the model holding the different versions.
             * E.g. versioning model: Post, versions model: PostVersion
             */
            'class_name'  => get_called_class() . 'Version',
            
            /**
             * Column in the versions table that holds the version count.
             */
            'versions_column' => 'version',
        ];
    }
}
