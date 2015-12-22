<?php
/**
 * Project: lavablog
 * User: stefanriedel
 * Date: 22.12.15
 * Time: 11:22
 */

namespace Lavablog\Engine\Core;

use Bosnadev\Repositories\Eloquent\Repository as BaseRepository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Collection;

abstract class Repository extends BaseRepository
{

    /**
     * @var \Illuminate\Cache\TaggedCache
     */
    protected $_cache;

    /**
     * @param App $app
     * @param Collection $collection
     * @throws \Bosnadev\Repositories\Exceptions\RepositoryException
     */
    public function __construct(App $app, Collection $collection)
    {
        parent::__construct($app, $collection);
        $this->_cache = $this->getCache();
    }


    /**
     *
     * creates the cache key for the current opereation
     *
     * @param array $attributes
     * @return string
     */
    public function getCacheKey(array $attributes = [])
    {
        $key = get_called_class();
        $key .= implode('_', $attributes);
        $key .= serialize($this->getCriteria());
        $key .= \Input::get('page', 1);
        $key = sha1($key);
        return $key;
    }

    /**
     * @return \Illuminate\Cache\TaggedCache
     */
    public function getCache()
    {
        return \Cache::tags(get_called_class());
    }

    /**
     * @param $id
     * @return mixed
     */
    public function delete($id)
    {
        $ret = parent::delete($id);
        $this->_cache->flush();
        return $ret;
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function create(array $data)
    {
        $data = $this->_clearFromFormHelpers($data);
        list($data, $relation_data) = $this->_prepareRelationsData($data);
        $ret = parent::create($data);
        $this->_saveRelations($relation_data, $ret->getKey());
        $this->flushCache();
        return $ret;
    }

    /**
     * @param array $data
     * @param $id
     * @param string $attribute
     * @return mixed
     */
    public function update(array $data, $id, $attribute = "id")
    {
        $data = $this->_clearFromFormHelpers($data);
        list($data, $relation_data) = $this->_prepareRelationsData($data);
        $ret =  parent::update($data, $id, $attribute);
        $this->_saveRelations($relation_data, $id);
        $this->flushCache();
        return $ret;
    }

    /**
     * @param  array $data
     * @param  $id
     * @return mixed
     */
    public function updateRich(array $data, $id)
    {
        $data = $this->_clearFromFormHelpers($data);
        list($data, $relation_data) = $this->_prepareRelationsData($data);
        $ret = parent::updateRich($data, $id);
        $this->_saveRelations($relation_data, $id);
        $this->flushCache();
        return $ret;
    }

    protected function _prepareRelationsData($data) {

        $relation_data = [];
        if(isset($data['translations'])) {
            $relation_data['translations'] = $data['translations'];
            unset($data['translations']);
        }
        return [$data, $relation_data];
    }

    protected function _saveRelations($relation_data, $id) {
        $this->_saveTranslationRelation($relation_data, $id);
    }

    /**
     *
     * will save the translations relations
     *
     * @param $data
     * @param null $id
     * @return mixed
     */
    protected function _savetranslations($data, $id = null) {
        if (isset($data['translations'])) {
            $translations_data = $data['translations'];
            unset($data['translations']);
            $model = $this->model->find($id);
            foreach ($translations_data as $locale => $locale_data) {
                $model->translate($locale)->fill($locale_data)->save();
            }
        }
        return $data;
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function all($columns = array('*'))
    {
        $args = [$columns];
        return $this->_cachedOrFromParent(__FUNCTION__, $args);
    }

    /**
     * @param $attribute
     * @param $value
     * @param array $columns
     * @return mixed
     */
    public function findBy($attribute, $value, $columns = array('*'))
    {
        $args = [$attribute, $value, $columns];
        return $this->_cachedOrFromParent(__FUNCTION__, $args);
    }

    /**
     * @param $id
     * @param array $columns
     * @return mixed
     */
    public function find($id, $columns = array('*'))
    {
        $args = [$id, $columns];
        return $this->_cachedOrFromParent(__FUNCTION__, $args);
    }

    /**
     * @param $attribute
     * @param $value
     * @param array $columns
     * @return mixed
     */
    public function findAllBy($attribute, $value, $columns = array('*'))
    {
        $args = [$attribute, $value, $columns];
        return $this->_cachedOrFromParent(__FUNCTION__, $args);
    }

    /**
     * Find a collection of models by the given query conditions.
     *
     * @param array $where
     * @param array $columns
     * @param bool $or
     *
     * @return \Illuminate\Database\Eloquent\Collection|null
     */
    public function findWhere($where, $columns = ['*'], $or = false)
    {
        $args = [$where, $columns, $or];
        return $this->_cachedOrFromParent(__FUNCTION__, $args);
    }

    /**
     * @param  string $value
     * @param  string $key
     * @return array
     */
    public function lists($value, $key = null)
    {
        $args = [$value, $key];
        return $this->_cachedOrFromParent(__FUNCTION__, $args);
    }

    /**
     * @param int $perPage
     * @param array $columns
     * @return mixed
     */
    public function paginate($perPage = 20, $columns = array('*'))
    {
        $args = [$perPage, $columns];
        return $this->_cachedOrFromParent(__FUNCTION__, $args);
    }


    /**
     * clearing the cache of this repository
     */
    public function flushCache()
    {
        $this->getCache()->flush();
    }

    /**
     * @param $method string the methode name of the parent method
     * @param $args array the method parameters
     * @return mixed
     */
    protected function _cachedOrFromParent($method, $args) {
        $cacheKey = $this->getCacheKey([get_called_class() . '::' . $method, serialize($args)]);
        if($this->_cache->has($cacheKey)) {
            $ret = $this->_cache->get($cacheKey);
        } else {
            $ret = call_user_func_array(array('parent', $method), $args);
            $this->_cache->forever($cacheKey, $ret);
        }
        return $ret;
    }

    /**
     * @param array $data
     * @return array
     */
    protected function _clearFromFormHelpers(array $data)
    {
        if (isset($data['_token'])) {
            unset($data['_token']);
        }
        if (isset($data['_method'])) {
            unset($data['_method']);
        }
        return $data;
    }

    /**
     * @param $relation_data
     * @param $id
     */
    protected function _saveTranslationRelation($relation_data, $id)
    {
        if (isset($relation_data['translations'])) {
            $model = $this->model->find($id);
            $language = \App::make(LanguageRepository::class);
            $language_list = $language->lists('iso', 'sort');
            $translations_data = $relation_data['translations'];
            foreach ($translations_data as $locale_key => $locale_data) {
                if(isset($language_list[$locale_key])) {
                    $model->translate($language_list[$locale_key])->fill($locale_data)->save();
                }
            }
        }
    }

}