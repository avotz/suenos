 
 <div class="navbar navbar-inverse navbar-fixed-top" role="navigation">
      <div class="container">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="/">Sueños de vida</a>
        </div>
        <div class="collapse navbar-collapse">
          <ul class="nav navbar-nav">
            <li class="{{ set_active('admin') }}">{{  link_to_route('dashboard','Inicio')  }}</li>
           @if (! Auth::guest())
            <li class="{{ set_active('admin/users') }}">{{ link_to_route('users','Usuarios') }}</li>
            <li class="{{ set_active('admin/categories') }}">{{ link_to_route('categories','Categorias') }}</li>
            <li class="{{ set_active('admin/products') }}">{{ link_to_route('products','Productos') }}</li>
            <li>{{ link_to_route('logout','Logout') }}</li>
           @else 
              <li class="{{ set_active('admin/login') }}">{{ link_to_route('login','Login') }}</li>
              
            @endif
          </ul>
        </div><!--/.nav-collapse -->
      </div>
    </div>