<?php namespace Suenos\Users;

use Carbon\Carbon;
use Suenos\Payments\Payment;
use Suenos\DbRepository;
use Suenos\Roles\Role;


class DbUserRepository extends DbRepository implements UserRepository {

    protected $model;

    function __construct(User $model)
    {
        $this->model = $model;
        $this->limit = 10;
        $this->membership_cost = 12000;
    }

    /** Save the user with a blank profile and assigned a role. Also verify a bonus system
     * @param $data
     * @return mixed
     */
    public function store($data)
    {
        $parent_id = $data['parent_id'];
        $data = $this->prepareData($data);

        $user = $this->model->create($data);

        //  dd($user);
        $role = (isset($data['role'])) ? $data['role'] : Role::whereName('member')->first();

        $user->createProfile();
        $user->assignRole($role);

        $user = $this->bonus($user, $parent_id);

        return $user;
    }

    /**
     * Update a user
     * @param $id
     * @param $data
     * @return \Illuminate\Support\Collection|static
     */
    public function update($id, $data)
    {
        $user = $this->model->findOrFail($id);

        if (! $data['parent_id'])
            $data['parent_id'] = null; //$this->prepareData($data);

        $roles[] = $data['role'];

        $user->fill($data);
        $user->save();
        $user->roles()->sync($roles);

        //$this->model->rebuild();

        return $user;
    }

    /**
     * Find User with your profile by Username
     * @param $username
     * @return mixed
     */
    public function findByUsername($username)
    {
        return $this->model->with('roles')->with('profiles')->whereUsername($username)->firstOrFail();
    }

    /**
     * Find all the users for the admin panel
     * @internal param $username
     * @param null $search
     * @return mixed
     */
    public function findAll($search = null)
    {

        if (! count($search) > 0) return $this->model->with('roles')->with('profiles')->paginate($this->limit);

        if (trim($search['q']))
        {
            $users = $this->model->Search($search['q']);
        } else
        {
            $users = $this->model;
        }

        if (isset($search['active']) && $search['active'] != "")
        {
            $users = $users->where('active', '=', $search['active']);
        }


        return $users->with('parent')->with('roles')->with('profiles')->orderBy('users.created_at', 'desc')->paginate($this->limit);

    }

    /**
     * Get the last user created for the dashboard panel
     * @return mixed
     */
    public function getLasts()
    {
        return $this->model->orderBy('users.created_at', 'desc')
            ->limit(6)->get(['users.id', 'users.username']);
    }

    /**
     * Generate a report with the user and your payments for month
     * @param $date
     * @internal param $month
     * @internal param $year
     * @return array
     */
    public function reportPaymentsByDay($date = null)
    {
        if ($date)
        {
            $today = array(
                Carbon::parse($date)->setTime(00, 00, 00),
                Carbon::parse($date)->setTime(23, 59, 59)
            );
        } else
        {
            $today = array(
                Carbon::now()->setTime(00, 00, 00),
                Carbon::now()->setTime(23, 59, 59)
            );
        }

        $payments = Payment::with('users.profiles')->where(function ($query) use ($today)
        {
            $query->whereBetween('created_at', $today)
                ->where('payment_type', '<>', 'MA');
        })->get();


        $paymentsArray = [];

        foreach ($payments as $payment)
        {
            $paymentArray = array(
                'id'                 => $payment->id,
                'Usuario Registrado' => $payment->users->created_at->toDateTimeString(),
                'Email'              => $payment->users->email,
                'Nombre'             => $payment->users->profiles->present()->fullname,
                'Cedula'             => $payment->users->profiles->ide,
                'Cuenta'             => $payment->users->profiles->number_account,
                'Monto pago'              => $payment->amount,
                'Fecha del pago'       => $payment->created_at->toDateTimeString(),
                'Fecha de la transferencia'       => $payment->transfer_date

            );

            $paymentsArray[] = $paymentArray;
        }

        //dd($paymentsArray);

        return $paymentsArray;

    }

    /**
     * Generate a report with the user and your payments for month
     * @param $month
     * @param $year
     * @return array
     */
    public function reportPaymentsByMonth($month, $year)
    {

        $users = $this->model->with('profiles')->get();

        $usersArray = [];
        foreach ($users as $user)
        {
            $usersOfRed = $user->children()->get()->lists('id');


            $paymentsOfUser =Payment::where(function ($query) use ($user, $month, $year)
            {
                $query->where('user_id','=', $user->id)
                    ->where(\DB::raw('MONTH(created_at)'), '=', $month)
                    ->where(\DB::raw('YEAR(created_at)'), '=', $year);
            });

            $paymentOfUser = $paymentsOfUser->sum(\DB::raw('amount'));

            if ($usersOfRed)
            {
                $paymentsOfRed =Payment::where(function ($query) use ($usersOfRed, $month, $year)
                {
                    $query->whereIn('user_id', $usersOfRed)
                        ->where(\DB::raw('MONTH(created_at)'), '=', $month)
                        ->where(\DB::raw('YEAR(created_at)'), '=', $year);
                });

                $gain = $paymentsOfRed->sum(\DB::raw('gain'));



                $membership_cost = ($paymentsOfRed->count()) ? $paymentsOfRed->first()->membership_cost : $this->membership_cost;



            } else
            {
                $gain = 0;
                $membership_cost = $this->membership_cost;

            }

            $userArray = array(
                'id'                 => $user->id,
                'Usuario Registrado' => $user->created_at->toDateTimeString(),
                'Email'              => $user->email,
                'Nombre'             => $user->profiles->present()->fullname,
                'Cedula'             => $user->profiles->ide,
                'Cuenta'             => $user->profiles->number_account,
                '# Afiliados'        => $user->children()->get()->count(),
                'Ganancia'           => $gain - $membership_cost,
                'Pago membresia'     => $paymentOfUser,
                'Mes'                => $month,
                'Año'                => $year
            );

            $usersArray[] = $userArray;

        }

        //dd($usersArray);

        return $usersArray;

    }

    /**
     * @param $data
     * @return array
     */
    public function prepareData($data)
    {
        // if (! $data['parent_id'])
        // {
        $data = array_except($data, array('parent_id'));

        // }


        return $data;
    }

    /**
     * Verify the bonus system
     * @param $user
     * @param $parent_id
     * @internal param $data
     * @return mixed
     */
    public function bonus($user, $parent_id)
    {
        if ($parent_id)
        {
            $parent_user = $this->model->findOrFail($parent_id);

            if ($parent_user->depth != 0)
            {
                if ($parent_user->immediateDescendants()->count() == 4 && $parent_user->bonus != 1) //quinto afiliado
                {
                    $parent_user->bonus = 1;
                    $parent_user->save();
                    $this->bonus($user, $parent_user->parent_id);
                } else if($parent_user->immediateDescendants()->count() == 9 && $parent_user->bonus != 2) //decimo afiliado
                {
                    $parent_user->bonus = 2;
                    $parent_user->save();
                    $this->bonus($user, $parent_user->parent_id);
                } else
                {
                    $user->parent_id = $parent_user->id;
                    $user->save();
                }
            } else
            {
                $user->parent_id = $parent_user->id;
                $parent_user->bonus = 1;
                $parent_user->save();
                $user->save();


            }


        }

        /*
         *  if ($parent_id)
        {
            $parent_user = $this->model->findOrFail($parent_id);

            if ($parent_user->depth != 0)
            {
                if ($parent_user->immediateDescendants()->count() == 4 && $parent_user->bonus != 1)
                {
                    $parent_user->bonus = 1;
                    $parent_user->save();
                    $this->bonus($user, $parent_user->parent_id);
                } else
                {
                    $user->parent_id = $parent_user->id;
                    $user->save();
                }
            } else
            {
                $user->parent_id = $parent_user->id;
                $parent_user->bonus = 1;
                $parent_user->save();
                $user->save();


            }


        }
         * */


        return $user;
    }

    //List of patners user for the modal view of user.

    public function list_patners($value = null, $search = null)
    {
        if ($search)
            $patners = ($value) ? $this->model->where('id', '<>', $value)->search($search)->paginate(8) : $this->model->paginate(8);
        else
            $patners = ($value) ? $this->model->where('id', '<>', $value)->paginate(8) : $this->model->paginate(8);

        return $patners;
    }

}