<?php
// app/Console/Commands/MakeServiceCommand.php
namespace EsperoSoft\Artisan\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class MakeServiceCommand extends Command
{
    protected $signature = 'make:service {name}';

    protected $description = 'Create a service class';

    public function handle()
    {
        $name = $this->argument('name');
        $serviceName = $this->generateServiceName(ucfirst($name));

        $servicesDirectory = app_path("Services");

        if (!File::isDirectory($servicesDirectory)) {
            File::makeDirectory($servicesDirectory, 0755, true);
        }

        $servicePath = app_path("Services/{$serviceName}.php");

        if (File::exists($servicePath)) {
            $this->error('Service already exists!');
            return;
        }

        File::put($servicePath, $this->generateServiceStub($serviceName));
        $this->info("Service {$serviceName} created successfully!");
    }

    protected function generateServiceName($name)
    {
        if (Str::endsWith($name, 'Service')) {
            return $name;
        }

        return $name . 'Service';
    }

    protected function generateServiceStub($name)
    {
        if ($name === 'CartService') {
            return $this->generateCartServiceStub($name);
        }
        if ($name === 'CompareService') {
            return $this->generateCompareServiceStub($name);
        }
        if ($name === 'WishService') {
            return $this->generateWishServiceStub($name);
        }
        if ($name === 'WishListService') {
            return $this->generateWishServiceStub($name);
        }
        if ($name === 'StripeService') {
            return $this->generateStripeServiceStub($name);
        }
        if ($name === 'PaypalService') {
            return $this->generatePaypalServiceStub($name);
        }

        // Default service stub
        $stub = <<<EOT
        <?php
        
        namespace App\Services;

        class {$name}
        {
            // Implement your service logic here
        }
        EOT;

        return $stub;
    }

    protected function generateCartServiceStub($name)
    {
        // Stub for CartService
        $stub = <<<EOT
        <?php
        
        namespace App\Services;
        
        use App\Models\Product; // Assurez-vous d'importer le modèle Product ici
        use Illuminate\Support\Facades\Session;
        
        class {$name}
        {
            public function addToCart(\$productId, \$quantity)
            {
                \$cart = Session::get('cart');
        
                if (isset(\$cart[\$productId])) {
                    \$cart[\$productId] += \$quantity;
                } else {
                    \$cart[\$productId] = \$quantity;
                }
        
                Session::put('cart', \$cart);
            }
            
            public function removeFromCart(\$productId, \$quantity)
            {
                \$cart = Session::get('cart');
        
                if (isset(\$cart[\$productId])) {
                    if (\$cart[\$productId] <= \$quantity) {
                        unset(\$cart[\$productId]);
                    } else {
                        \$cart[\$productId] -= \$quantity;
                    }
        
                    Session::put('cart', \$cart);
                }
            }
        
            public function clearCart()
            {
                Session::forget('cart');
            }
        
            public function getCartDetails()
            {
                \$cart = Session::get('cart', []);
                \$result = [
                    'items' => [],
                    'sub_total' => 0,
                    'cart_count' => 0,
                ];
        
                foreach (\$cart as \$productId => \$quantity) {
                    \$product = Product::find(\$productId);
                    if (\$product) {
                        \$subTotal = \$product->price * \$quantity;
                        \$result['items'][] = [
                            'product' => [
                                'id' => \$product->id,
                                'name' => \$product->name,
                                'price' => \$product->price,
                                // Ajoutez d'autres attributs du produit ici
                            ],
                            'quantity' => \$quantity,
                            'sub_total' => \$subTotal,
                        ];
                        \$result['sub_total'] += \$subTotal;
                        \$result['cart_count'] += \$quantity;
                    }
                }
        
                return \$result;
            }
        
        }
        EOT;

        return $stub;
    }
    protected function generateCompareServiceStub($name)
    {
        $stub = <<<EOT
            <?php

            namespace App\Services;

            use App\Models\Product; // Make sure to import the Product model here
            use Illuminate\Support\Facades\Session;

            class {$name}
            {
                public function addProductToCompare(\$productId)
                {
                    \$compareProducts = Session::get('compare', []);

                    if (!in_array(\$productId, \$compareProducts)) {
                        \$compareProducts[] = \$productId;
                        Session::put('compare', \$compareProducts);
                    }
                }

                public function removeProductFromCompare(\$productId)
                {
                    \$compareProducts = Session::get('compare', []);

                    \$index = array_search(\$productId, \$compareProducts);
                    if (\$index !== false) {
                        unset(\$compareProducts[\$index]);
                        Session::put('compare', array_values(\$compareProducts));
                    }
                }

                public function getComparedProducts()
                {
                    return Session::get('compare', []);
                }
                public function getComparedProductsDetails()
                {
                    \$compareProducts = Session::get('compare', []);
                    \$comparedDetails = [];

                    foreach (\$compareProducts as \$productId) {
                        \$product = Product::find(\$productId);

                        if (\$product) {
                            \$comparedDetails[] = [
                                'id' => \$product->id,
                                'name' => \$product->name,
                                'price' => \$product->price,
                                // Ajoutez d'autres attributs du produit ici
                            ];
                        }
                    }

                    return \$comparedDetails;
                }

                public function clearComparedProducts()
                {
                    Session::forget('compare');
                }
            }
            EOT;

        return $stub;
    }

    protected function generateWishServiceStub($name)
    {
        $stub = <<<EOT
            <?php

            namespace App\Services;

            use App\Models\Product; // Make sure to import the Product model here
            use Illuminate\Support\Facades\Session;

            class {$name}
            {
                public function addProductToWish(\$productId)
                {
                    \$wishProducts = Session::get('wish', []);

                    if (!in_array(\$productId, \$wishProducts)) {
                        \$wishProducts[] = \$productId;
                        Session::put('wish', \$wishProducts);
                    }
                }

                public function removeProductFromWish(\$productId)
                {
                    \$wishProducts = Session::get('wish', []);

                    \$index = array_search(\$productId, \$wishProducts);
                    if (\$index !== false) {
                        unset(\$wishProducts[\$index]);
                        Session::put('wish', array_values(\$wishProducts));
                    }
                }

                public function getWishedProducts()
                {
                    return Session::get('wish', []);
                }

                public function getWishedProductsDetails()
                {
                    \$wishProducts = Session::get('wish', []);
                    \$wishedDetails = [];

                    foreach (\$wishProducts as \$productId) {
                        \$product = Product::find(\$productId);

                        if (\$product) {
                            \$wishedDetails[] = [
                                'id' => \$product->id,
                                'name' => \$product->name,
                                'price' => \$product->price,
                                // Ajoutez d'autres attributs du produit ici
                            ];
                        }
                    }

                    return \$wishedDetails;
                }

                public function clearWishedProducts()
                {
                    Session::forget('wish');
                }
            }
            EOT;

        return $stub;
    }
    protected function generateStripeServiceStub($name)
    {
        $stub = <<<EOT
        <?php

        namespace App\Services;

        use App\Models\Method;
        use Illuminate\Support\Facades\App;

        class {$name}
        {
            private \$method;

            public function __construct()
            {
                // Vérifie si la méthode Stripe est disponible
                \$this->method = Method::where('name', 'Stripe')->first();
            }

            // Implémentez ici la logique de votre service
            public function getPublicKey()
            {
                if (\$this->method) {
                    return App::environment('production')
                        ? \$this->method->prod_public_key
                        : \$this->method->test_public_key;
                }

                return null; // Gérer le cas où la méthode n'est pas trouvée en base de données
            }

            public function getPrivateKey()
            {
                if (\$this->method) {
                    return App::environment('production')
                        ? \$this->method->prod_private_key
                        : \$this->method->test_private_key;
                }

                return null; // Gérer le cas où la méthode n'est pas trouvée en base de données
            }
        }
        EOT;

        return $stub;
    }

    protected function generatePaypalServiceStub($name)
    {
        $stub = <<<EOT
        <?php

        namespace App\Services;

        use App\Models\Method;
        use Illuminate\Support\Facades\App;

        class {$name}
        {
            private \$method;

            public function __construct()
            {
                // Vérifie si la méthode Paypal est disponible
                \$this->method = Method::where('name', 'Paypal')->first();
            }

            // Implémentez ici la logique de votre service Paypal
            public function getPublicKey()
            {
                if (\$this->method) {
                    return App::environment('production')
                        ? \$this->method->prod_public_key
                        : \$this->method->test_public_key;
                }

                return null; // Gérer le cas où la méthode n'est pas trouvée en base de données
            }

            public function getPrivateKey()
            {
                if (\$this->method) {
                    return App::environment('production')
                        ? \$this->method->prod_private_key
                        : \$this->method->test_private_key;
                }

                return null; // Gérer le cas où la méthode n'est pas trouvée en base de données
            }
        }
        EOT;

        return $stub;
    }

}
