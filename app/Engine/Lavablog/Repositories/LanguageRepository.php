<?php
/**
 * Project: lavablog
 * User: stefanriedel
 * Date: 22.12.15
 * Time: 11:31
 */

namespace Lavablog\Engine\Lavablog\Repositories;


use Lavablog\Engine\Core\Repository;
use Lavablog\Language;

class LanguageRepository extends Repository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return Language::class;
    }

    public function getMaxSort() {
        return \DB::table($this->model->getTable())->max('sort');
    }
}