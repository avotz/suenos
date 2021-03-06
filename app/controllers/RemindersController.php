<?php

class RemindersController extends BaseController {

    /**
     * Display the password reminder view.
     *
     * @return Response
     */
    public function getRemind()
    {
        return View::make('password.remind');
    }

    /**
     * Handle a POST request to remind a user of their password.
     *
     * @return Response
     */
    public function postRemind()
    {
        $response = Password::remind(Input::only('email'), function ($message)
        {
            $message->subject('Tu cambio de contraseña');
        });
        switch ($response)
        {
            case Password::INVALID_USER:
                Flash::error(Lang::get($response));

                return Redirect::back();

            case Password::REMINDER_SENT:
                Flash::message(Lang::get($response));

                return Redirect::back();
        }
    }

    /**
     * Display the password reset view for the given token.
     *
     * @param  string $token
     * @return Response
     */
    public function getReset($token = null)
    {
        if (is_null($token)) App::abort(404);

        return View::make('password.reset')->with('token', $token);
    }

    /**
     * Handle a POST request to reset a user's password.
     *
     * @return Response
     */
    public function postReset()
    {
        $credentials = Input::only(
            'email', 'password', 'password_confirmation', 'token'
        );

        Password::validator(function ($credentials)
        {
            return strlen($credentials['password']) >= 3;
        });
        $response = Password::reset($credentials, function ($user, $password)
        {
            $user->password = $password;

            $user->save();
        });

        switch ($response)
        {
            case Password::INVALID_PASSWORD:
            case Password::INVALID_TOKEN:
            case Password::INVALID_USER:
                Flash::error(Lang::get($response));

                return Redirect::back();

            case Password::PASSWORD_RESET:
                Flash::success('Tu contraseña ha sido cambiada. Puedes iniciar sesion ahora');

                return Redirect::to('/');
        }
    }

}
