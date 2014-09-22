<?php namespace Suenos\Categories;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Suenos\DbRepository;



class DbCategoryRepository extends DbRepository implements CategoryRepository  {

    protected $model;

    function __construct(Category $model)
    {
        $this->model = $model;
        $this->limit = 10;
    }

    public function store($data)
    {
        $data = $this->prepareData($data);
        $data['image'] = ($data['image']) ? $this->storeImage($data['image'], $data['name'], 'categories', 200, null) : '';

        return $this->model->create($data);
    }

    public function update($id, $data)
    {
        $category = $this->model->findOrFail($id);
        $data = $this->prepareData($data);
        $data['image'] = ($data['image']) ? $this->storeImage($data['image'], $data['name'], 'categories', 200, null) : $category->image;

        $category->fill($data);
        $category->save();

        return $category;
    }
    public function findById($id)
    {
        return $this->model->findOrFail($id);
    }

    public function destroy($id)
    {
        $category = $this->findById($id);
        $image_delete = $category->image;
        $category->delete();

        File::delete(dir_photos_path('categories') . $image_delete);
        File::delete(dir_photos_path('categories') . 'thumb_' . $image_delete);

        return $category;
    }


    public function getLasts()
    {
        return $this->model->join('category_product', 'category_product.category_id', '=', 'categories.id')
            ->groupBy('categories.name')
            ->orderBy('categories.created_at', 'desc')
            ->limit(6)->get(['categories.id', 'categories.name', \DB::raw('count(*) as products_count')]);
    }

    public function getAll($search)
    {
        if (isset($search['q']) && ! empty($search['q']))
        {
            $categories = $this->model->Search($search['q']);
        } else
        {
            $categories = $this->model;
        }

        if (isset($search['published']) && $search['published'] != "")
        {
            $categories = $categories->where('published', '=', $search['published']);
        }

        return $categories->orderBy('lft')->paginate($this->limit);
    }

    public function getParents()
    {
        $all = $this->model->select('id', 'name', 'depth')->orderBy('lft')->get();

        $result = array();

        foreach ($all as $item)
        {
            $name = $item->name;
            if ($item->depth > 0) $name = str_repeat('—', $item->depth) . ' ' . $name;
            $result[$item->id] = $name;
        }

        return $result;
    }

    /**
     * @param $data
     * @return mixed
     */
    private function prepareData($data)
    {
        if(!$data['parent_id'])
        {
            $data = array_except($data, array('parent_id'));
        }

        $data['slug'] = Str::slug($data['name']);

        return $data;
    }
}