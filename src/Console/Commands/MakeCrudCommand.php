<?php

namespace EsperoSoft\Artisan\Console\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class MakeCrudCommand extends Command
{
    protected $entity = '';
    protected $entityNames = '';

    protected $signature = 'make:crud {entities?*}
                                {--all : Generate CRUD for all entities}'; // Ajout de l'option --all

    protected $description = 'Generate CRUD for specified entity/entities';

    public function handle()
    {
        if ($this->option('all')) {
            $entities = $this->getAllEntities(); // Fonction pour récupérer toutes les entités disponibles

            foreach ($entities as $entity) {
                $this->generateCRUD($entity);
            }
        } else {
            $entities = $this->argument('entities');

            foreach ($entities as $entity) {
                $this->generateCRUD($entity);
            }
        }
    }

    protected function generateCRUD($entity)
    {
        $this->entity = Str::lower($entity);
        $this->entityNames = Str::plural($this->entity);

        // Logique pour générer les fichiers du CRUD ici pour chaque entité
        // Par exemple, génération de contrôleurs, de routes, de vues, etc.
        // Utilisez les outils de Laravel comme Artisan::call() pour générer des resources.

        $this->createController();
        $this->createViews();
        $this->createRoutes();
        $this->addNavItem($this->entity);

        $this->info("CRUD generated successfully for {$this->entity}.");
    }

    protected function getAllEntities()
    {
        // Chemin vers le répertoire des modèles de votre application
        $modelsDirectory = app_path('Models');

        // Vérifier si le répertoire des modèles existe
        if (!File::exists($modelsDirectory) || !File::isDirectory($modelsDirectory)) {
            return [];
        }

        // Lister tous les fichiers dans le répertoire des modèles
        $modelFiles = File::files($modelsDirectory);

        $modelNames = [];

        foreach ($modelFiles as $file) {
            // Récupérer le nom du fichier sans l'extension .php
            $modelName = pathinfo($file->getFilename(), PATHINFO_FILENAME);

            // Ajouter le nom du modèle à la liste
            $modelNames[] = $modelName;
        }

        return $modelNames;
    }



    public function createController()
    {

        $EntityName = ucfirst($this->entity);
        $entityName = lcfirst($this->entity);
        $entityNames = Str::plural($this->entity);

        $storeFile = '';
        $updateFile = '';
        $deleteFile = '';
        $manageFile = '';

        if (in_array('imageUrl', $this->getFields())) {
            $storeFile .= <<<EOD
                if (\$req->hasFile('imageUrl')) {
                    \$data['imageUrl'] = \$this->handleImageUpload(\$req->file('imageUrl'));
                }
            EOD;
            $updateFile .= <<<EOD
                if (\$req->hasFile('imageUrl')) {
                    // Suppression de l'ancienne image si elle existe
                    if (\${$entityName}->imageUrl) {
                        Storage::disk('public')->delete(\${$entityName}->imageUrl);
                    }
                    \$data['imageUrl'] = \$this->handleImageUpload(\$req->file('imageUrl'));
                }
            EOD;
            $deleteFile .= <<<EOD
                if (\${$entityName}->imageUrl) {
                    Storage::disk('public')->delete(\${$entityName}->imageUrl);
                }
            EOD;
        }
        if (in_array('imageUrls', $this->getFields())) {
            $storeFile .= <<<EOD
                if (\$req->hasFile('imageUrls')) {
                    \$data['imageUrls'] = json_encode(\$this->handleImageUpload(\$req->file('imageUrls')));
                }
            EOD;
            $updateFile .= <<<EOD
                if (\$req->hasFile('imageUrls')) {
                    \$uploadedImages = \$this->handleImageUpload(\$req->file('imageUrls'));
                    // Suppression des anciennes images s'il en existe
                    if (\${$entityName}->imageUrls && is_array(\${$entityName}->imageUrls)) {
                        foreach (\${$entityName}->imageUrls as \$imageUrl) {
                            Storage::disk('public')->delete(\$imageUrl);
                        }
                    }
                    \$data['imageUrls'] = json_encode(\$uploadedImages);
                }
            EOD;
            $deleteFile .= <<<EOD
                if (\${$entityName}->imageUrls) {
                    foreach (\${$entityName}->imageUrls as \$image) {
                        Storage::disk('public')->delete(\$image);
                    }
                }
                EOD;
        }

        if (in_array('imageUrl', $this->getFields()) || in_array('imageUrls', $this->getFields())) {
            $manageFile .= <<<EOD
                private function handleImageUpload(\Illuminate\Http\UploadedFile|array \$images): string|array
                {
                    if (is_array(\$images)) {
                        \$uploadedImages = [];
                        foreach (\$images as \$image) {
                            \$imageName = uniqid() . '_' . \$image->getClientOriginalName();
                            \$image->storeAs('images', \$imageName, 'public');
                            \$uploadedImages[] = 'images/' . \$imageName;
                        }
                        return \$uploadedImages;
                    } else {
                        \$imageName = uniqid() . '_' . \$images->getClientOriginalName();
                        \$images->storeAs('images', \$imageName, 'public');
                        return 'images/' . \$imageName;
                    }
                }
            EOD;
        }

        $contentController = <<<EOD
        <?php

        namespace App\Http\Controllers;

        use App\Models\\$EntityName;
        use Illuminate\View\View;
        use Illuminate\Http\Request;
        use Illuminate\Http\RedirectResponse;
        use App\Http\Requests\\{$EntityName}FormRequest;
        use Illuminate\Support\Facades\Storage;

        class {$EntityName}Controller extends Controller
        {
            public function index(): View
            {
                \$$entityNames = $EntityName::orderBy('created_at', 'desc')->paginate(5);
                return view('{$entityNames}/index', ['$entityNames' => \$$entityNames]);
            }

            public function show(\$id): View
            {
                \$$entityName = $EntityName::findOrFail(\$id);

                return view('{$entityNames}/show',['$entityName' => \$$entityName]);
            }
            public function create(): View
            {
                return view('{$entityNames}/create');
            }

            public function edit(\$id): View
            {
                \$$entityName = $EntityName::findOrFail(\$id);
                return view('{$entityNames}/edit', ['$entityName' => \$$entityName]);
            }

            public function store({$EntityName}FormRequest \$req): RedirectResponse
            {
                \$data = \$req->validated();

                {$storeFile}

                \$$entityName = $EntityName::create(\$data);
                return redirect()->route('admin.{$entityName}.show', ['id' => \${$entityName}->id]);
            }

            public function update($EntityName \$$entityName, {$EntityName}FormRequest \$req)
            {
                \$data = \$req->validated();

                {$updateFile}

                \${$entityName}->update(\$data);

                return redirect()->route('admin.{$entityName}.show', ['id' => \${$entityName}->id]);
            }

            public function updateSpeed($EntityName \$$entityName, Request \$req)
            {
                foreach (\$req->all() as \$key => \$value) {
                    \${$entityName}->update([
                        \$key => \$value
                    ]);
                }

                return [
                    'isSuccess' => true,
                    'data' => \$req->all()
                ];
            }

            public function delete($EntityName \$$entityName)
            {
                {$deleteFile}
                \${$entityName}->delete();

                return [
                    'isSuccess' => true
                ];
            }

            {$manageFile}
        }
        EOD;

        $merges = '';
        $rules = '';
        $fields = $this->getFields();
        $count = count($fields);

        foreach ($fields as $index => $field) {
            if (Str::startsWith(Str::lower($field), 'is')) {
                $merges .= "'$field' => \$this->input('$field') ? 'true' : 'false',\n\t\t\t";
            }
            if (stripos($field, 'slug') !== false) {
                if (in_array('title', $fields)) {
                    $merges .= "'$field' => \Illuminate\Support\Str::slug(\$this->input('title')),\n\t\t\t";
                } elseif (in_array('name', $fields)) {
                    $merges .= "'$field' => \Illuminate\Support\Str::slug(\$this->input('name')),\n\t\t\t";
                }
            }
        }
        foreach ($fields as $index => $field) {

            $format = "string";

            if (stripos($field, 'imageUrl') !== false) {
                $format = 'image|mimes:webp,jpeg,png,jpg,gif|max:2048';
            } elseif (stripos($field, 'email') !== false) {
                $format = 'email';
            } elseif (stripos($field, 'password') !== false) {
                $format = 'min:8';
            } elseif (Str::startsWith(Str::lower($field), 'is') !== false) {
                $format = 'in:true,false|nullable';
            } elseif (stripos($field, 'slug') !== false) {
                $format = '';
            }


            if (stripos($field, 'imageUrls') !== false) {
                $rules .= "'$field' => \$isRequired.'array|max:5',\n\t\t\t";
                $rules .= "'$field.*' => 'image|mimes:webp,jpeg,png,jpg,gif|max:2048'";

            } else {
                $rules .= "'$field' => \$isRequired.'$format'";
            }

            if ($index === $count - 1) {
                $rules .= "\n\t\t\t";
            } else {
                $rules .= ",\n\t\t\t";
            }
        }

        $contentRequests = <<<EOD
        <?php

        namespace App\Http\Requests;

        use Illuminate\Foundation\Http\FormRequest;

        class {$EntityName}FormRequest extends FormRequest
        {
            /**
             * Determine if the user is authorized to make this request.
             */
            public function authorize(): bool
            {
                return true;
            }

            /**
             * Get the validation rules that apply to the request.
             *
             * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
             */
            public function rules(): array
            {
                \$isRequired = request()->isMethod("POST") ?"required|": "";
                return [
                    //
                    $rules
                ];
            }
            public function prepareForValidation()
            {
                \$this->merge([
                    $merges
                ]);
            }
        }
        EOD;

        $controllerPath = app_path('Http/Controllers/' . $EntityName . 'Controller.php');
        $formRequestPath = app_path('Http/Requests/' . $EntityName . 'FormRequest.php');

        // Vérification de l'existence des fichiers
        if (!file_exists($controllerPath)) {
            file_put_contents($controllerPath, $contentController);
        }

        if (!file_exists($formRequestPath)) {
            file_put_contents($formRequestPath, $contentRequests);
        }
    }
    public function createViews()
    {
        $this->generateBase();
        $directory = resource_path('views/' . Str::plural($this->entity));
        // Vérifier si le dossier existe déjà
        if (!File::isDirectory($directory)) {
            // Si le dossier n'existe pas, le créer avec les permissions
            File::makeDirectory($directory, 0755, true);
        }

        $this->info("#######################################");
        $this->info("###       Create CRUD Files         ###");
        $this->info("#######################################");
        $this->createViewForm();
        $this->createViewIndex();
        $this->createViewCreate();
        $this->createViewEdit();
        $this->createViewShow();
    }



    protected function createViewShow()
    {
        $content = '';
        $entityName = ucfirst($this->entity);
        $entityNames = Str::plural($this->entity);
        $entityInstance = Str::camel($this->entity); // Instance de l'entité
        foreach ($this->getFields() as $field) {
            $Field = ucfirst($field);
            if ($content !== '') {
                $content .= "\n";
            }

            if ($field === "imageUrl") {
                $content .= <<<HTML
                    <tr>
                        <th>$Field</strong></th>
                        <td>
                            <div class="form-group d-flex" id="preview_imageUrl" style="max-width: 100%;">
                                <img src="{{ Str::startsWith(\${$entityInstance}->$field, 'http') ? \${$entityInstance}->$field : Storage::url(\${$entityInstance}->$field) }}"
                                     alt="Prévisualisation de l'image"
                                     style="max-width: 100px; display: block;">
                            </div>
                        </td>
                     </tr>
                HTML;

            } elseif ($field === "imageUrls") {
                $content .= <<<HTML
                    <tr>
                        <th>$Field</th>
                        <td>
                            <div class="form-group d-flex" id="preview_imageUrls" style="max-width: 100%;">
                                <!-- Assurez-vous que \$this->{$entityInstance}->$field est un tableau d'URLs -->
                               @foreach (\${$entityInstance}->$field() as \$url)
                                    <img src="{{ Str::startsWith(\$url, 'http') ? \$url : Storage::url(\$url) }}"
                                         alt="Prévisualisation de l'image"
                                         style="max-width: 100px; display: block;"
                                         />
                                @endforeach
                            </div>

                        </td>
                    </tr>
                HTML;

            } elseif (Str::startsWith(Str::lower($field), 'is')) {
                $content .= <<<HTML
                    <tr>
                        <th>$Field</th> 
                        <td>
                            <div class="form-check form-switch">
                                <input name="{$field}" disabled id="{$field}" value="true" data-bs-toggle="toggle"  {{ \$${entityInstance}->{$field} == 'true' ? 'checked' : '' }} class="form-check-input" type="checkbox" role="switch" />
                            </div>
                        </td>
                    </tr>
                HTML;
            } 
            elseif(in_array($field, ['content', 'moreDescription', 'additionalInfos'])) {
                $content .= <<<HTML
                    <tr>
                        <th>$Field</th> 
                        <td>{!! \$$entityInstance->$field !!}</td>
                </tr>
                HTML;

            }else {
                $content .= <<<HTML
                    <tr>
                        <th>$Field</th> 
                        <td>{{ \$$entityInstance->$field }}</td>
                </tr>
                HTML;
            }
        }

        $content .= "\n\t";

        $viewContent = <<<EOD
            @extends('admin')

            @section('styles')
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
            @endsection

            @section('content')
                <div >
                    <h3>Show $entityName</h3>

                    <a href="{{ route('admin.{$entityInstance}.index') }}" class="btn btn-success my-1">
                        Home
                    </a>
                    <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody>
                            $content
                        </tbody>
                    </table>

                    <div>
                        <a href="{{ route('admin.{$entityInstance}.edit', ['id' => \${$entityInstance}->id]) }}" class="btn btn-primary my-1">
                            <i class="fa-solid fa-pen-to-square"></i>  Edit
                        </a>
                    </div>
                </div>
            @endsection
            EOD;

        File::put(resource_path('views/' . $this->entityNames . '/show.blade.php'), $viewContent);
        $this->info('5- Show Data Template : resources/views/' . $this->entityNames . '/show.blade.php');
    }

    protected function createViewCreate()
    {
        $entityName = ucfirst($this->entity);
        $entityNames = Str::plural($this->entity);
        $entityInstance = Str::camel($this->entity); // Instance de l'entité


        $viewContent = <<<EOD
        @extends('admin')

        @section('content')
        <div >
            <h3>Create {$entityName}</h3>
            <a href="{{ route('admin.{$entityInstance}.index') }}" class="btn btn-success my-1">
                    Home
            </a>
            @include('{$this->entityNames}/{$entityInstance}Form')
                </div>
        @endsection

        EOD;

        File::put(resource_path('views/' . $this->entityNames . '/create.blade.php'), $viewContent);
        $this->info('3- Create Data Template : resources/views/' . $this->entityNames . '/create.blade.php');
    }
    protected function createViewEdit()
    {
        $entityName = ucfirst($this->entity);
        $entityNames = Str::plural($this->entity);
        $entityInstance = Str::camel($this->entity); // Instance de l'entité


        $viewContent = <<<EOD
        @extends('admin')

        @section('content')
            <div >
                <h3>Edit {$entityName}</h3>
                <a href="{{ route('admin.{$entityInstance}.index') }}" class="btn btn-success my-1">
                        Home
                </a>
                @include('{$this->entityNames}/{$entityInstance}Form', ['$entityInstance' => \$$entityInstance])
            </div>
        @endsection
        EOD;

        File::put(resource_path('views/' . $this->entityNames . '/edit.blade.php'), $viewContent);
        $this->info('4- Edit Data Template : resources/views/' . $this->entityNames . '/edit.blade.php');
    }
    protected function createViewForm()
    {
        $entityName = ucfirst($this->entity);
        $entityNames = Str::plural($this->entity); // Instance de l'entité
        $entityInstance = Str::camel($this->entity); // Instance de l'entité

        $multipart = '';
        $formAction = "{{ isset(\${$entityInstance}) ? route('admin.{$entityInstance}.update', ['{$entityInstance}' => \${$entityInstance}->id]) : route('admin.{$entityInstance}.store') }}";

        if (in_array('imageUrl', $this->getFields()) || in_array('imageUrls', $this->getFields())) {
            $multipart .= 'enctype="multipart/form-data"';
        }
        $content = <<<HTML
            <form action="{$formAction}" method="POST" $multipart>
                @csrf
                @if(isset(\${$entityInstance}))
                    @method('PUT')
                @endif
        HTML;

        foreach ($this->getFields() as $field) {
            $Field = ucfirst($field);
            if (in_array($field, ['content', 'moreDescription', 'additionalInfos'])) {

                $content .= <<<HTML
                    <div class="mb-3">
                        <label for="{$field}" class="form-label">{$Field}</label>
                        <textarea name="{$field}" class="form-control" id="{$field}" aria-describedby="{$field}Help">{{ old('{$field}', isset(\$${entityInstance}) ? \$${entityInstance}->{$field} : '') }}</textarea>

                        @error('{$field}')
                            <div class="error text-danger">
                                {{ \$message }}
                            </div>
                        @enderror
                    </div>
                HTML;

            } else if (strtolower($field) === "imageurl") {

                $content .= <<<HTML
                    <div class="mb-3">
                        <button type="button" class="btn btn-success btn-file my-1" onclick="triggerFileInput('{$field}')">
                            Add file :  ({$Field})
                        </button>
                        <input type="file" name="{$field}" value="{{ old('{$field}', isset(\$${entityInstance}) ? \$${entityInstance}->{$field} : '') }}" class="visually-hidden form-control imageUpload" id="{$field}" aria-describedby="{$field}Help"/>

                        <div class="form-group d-flex" id="preview_{$field}" style="max-width: 100%;">
                        </div>
                        @error('{$field}')
                            <div class="error text-danger">
                                {{ \$message }}
                            </div>
                        @enderror
                    </div>
                HTML;
            } else if (strtolower($field) === "imageurls") {

                $content .= <<<HTML
                    <div class="mb-3">
                        <button type="button" class="btn btn-success btn-file my-1" onclick="triggerFileInput('{$field}')">
                            Add files :  ({$Field})
                        </button>
                        <input type="file" name="{$field}[]" class="form-control imageUpload visually-hidden" id="{$field}" aria-describedby="{$field}Help" multiple />
                        <div class="form-group  hstack gap-3" id="preview_{$field}" style="max-width: 100%;"></div>
                        @error('{$field}')
                            <div class="error text-danger">{{ \$message }}</div>
                        @enderror
                    </div>
                HTML;
            } else if (strtolower($field) === "slug") {

                $content .= <<<HTML
                HTML;
            } else if (strtolower($field) === "password") {

                $content .= <<<HTML
                    <div class="mb-3">
                        <label for="{$field}" class="form-label">{$Field}</label>
                        <input type="password" name="{$field}" placeholder="{$Field} ..." value="{{ old('{$field}', isset(\$${entityInstance}) ? \$${entityInstance}->{$field} : '') }}" class="form-control" id="{$field}" aria-describedby="{$field}Help" required/>

                        @error('{$field}')
                            <div class="error text-danger">
                                {{ \$message }}
                            </div>
                        @enderror
                    </div>
                HTML;
            } else if (Str::startsWith(Str::lower($field), "is")) {

                $content .= <<<HTML
                    <div class="mb-3 d-flex gap-2">
                        <label for="{$field}" class="form-label">{$Field}</label>
                        <div class="form-check form-switch">
                            <input name="{$field}" id="{$field}" value="true" data-bs-toggle="toggle"  {{ old('{$field}', isset(\$${entityInstance}) && \$${entityInstance}->{$field} == 'true' ? 'checked' : '') }} class="form-check-input" type="checkbox" role="switch" />
                        </div>
                        {{-- <select class="form-control" name="{$field}" id="{$field}">
                            <option value="true" {{ old('{$field}', isset(\$${entityInstance}) && \$${entityInstance}->{$field} == 'true' ? 'selected' : '') }}>Yes</option>
                            <option value="false" {{ old('{$field}', isset(\$${entityInstance}) && \$${entityInstance}->{$field} == 'false' ? 'selected' : '') }}>No</option>
                        </select> --}}

                        @error('{$field}')
                            <div class="error text-danger">
                                {{ \$message }}
                            </div>
                        @enderror
                    </div>
                HTML;

            } else {
                $content .= <<<HTML
                    <div class="mb-3">
                        <label for="{$field}" class="form-label">{$Field}</label>
                        <input type="text"  placeholder="{$Field} ..."  name="{$field}" value="{{ old('{$field}', isset(\$${entityInstance}) ? \$${entityInstance}->{$field} : '') }}" class="form-control" id="{$field}" aria-describedby="{$field}Help" required/>

                        @error('{$field}')
                            <div class="error text-danger">
                                {{ \$message }}
                            </div>
                        @enderror
                    </div>
                HTML;


            }
        }

        $content .= <<<HTML
            <a href="{{ route('admin.{$entityInstance}.index') }}" class="btn btn-danger mt-1">
                Cancel
            </a>
            <button class="btn btn-primary mt-1"> {{ isset(\${$entityInstance}) ? 'Update' : 'Create' }}</button>
         </form>
        HTML;


        $viewContent = <<<EOD
            @section('styles')
                <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
            @endsection
            <div class="row">
            <div class="col-md-8">
            $content
            </div>
            <div class="col-md-4">
            <a  class="btn btn-danger mt-1" href="{{ route('admin.{$entityInstance}.index') }}">
            Cancel
        </a>
        <button class="btn btn-primary mt-1"> {{ isset(\${$entityInstance}) ? 'Update' : 'Create' }}</button>
            </div>
            </div>

            @section('scripts')
            <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4=" crossorigin="anonymous"></script>
            <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
            <script src="https://cdn.ckeditor.com/ckeditor5/40.1.0/classic/ckeditor.js"></script>

            <script>
                const textareas = document.querySelectorAll('textarea');
                textareas.forEach((textarea) => {
                    ClassicEditor
                        .create(textarea)
                        .catch(error => {
                            console.error(error);
                        });
                });

                $(document).ready(function() {
                    $('select').select2();
                });
                function triggerFileInput(fieldId) {
                    const fileInput = document.getElementById(fieldId);
                    if (fileInput) {
                        fileInput.click();
                    }
                }

                const imageUploads = document.querySelectorAll('.imageUpload');
                imageUploads.forEach(function(imageUpload) {
                    imageUpload.addEventListener('change', function(event) {
                        event.preventDefault()
                        const files = this.files; // Récupérer tous les fichiers sélectionnés
                        console.log(files)
                        if (files && files.length > 0) {
                            const previewContainer = document.getElementById('preview_' + this.id);
                            previewContainer.innerHTML = ''; // Effacer le contenu précédent

                            for (let i = 0; i < files.length; i++) {
                                const file = files[i];
                                if (file) {
                                    const reader = new FileReader();
                                    const img = document.createElement('img'); // Créer un élément img pour chaque image

                                    reader.onload = function(event) {
                                        img.src = event.target.result;
                                        img.alt = "Prévisualisation de l'image"
                                        img.style.maxWidth = '100px';
                                        img.style.display = 'block';
                                    }

                                    reader.readAsDataURL(file);
                                    previewContainer.appendChild(img); // Ajouter l'image à la prévisualisation
                                    console.log({img})
                                    console.log({previewContainer})
                                }
                            }
                            console.log({previewContainer})
                        }
                    });
                });
            </script>
            @endsection
        EOD;


        File::put(resource_path('views/' . $this->entityNames . '/' . $this->entity . 'Form.blade.php'), $viewContent);
        $this->info('1- Create Form template : resources/views/' . $this->entityNames . '/' . $this->entity . 'Form.blade.php');
    }

    protected function createViewIndex()
    {

        $thead = '';
        $entityName = ucfirst($this->entity);
        $EntityName = ucfirst($this->entity);
        $entityNames = Str::plural($this->entity);
        $EntityNames = ucfirst($entityNames);
        $entityInstance = Str::camel($this->entity); // Instance de l'entité

        $thead .= "<th scope=\"col\">N#</th>\n\t\t\t\t\t\t";

        foreach ($this->getFields() as $field) {
            $value = ucfirst($field);
            $thead .= "<th scope=\"col\">$value</th>\n\t\t\t\t\t\t";
        }
        $thead .= "\n\t\t\t\t\t\t<th scope=\"col\">Actions</th>";

        $tbody = "@foreach(\$$entityNames as \${$this->entity})\n\t\t\t\t\t\t";
        $tbody .= "<tr>";
        $tbody .= "<td>{{ \${$this->entity}->id }}</td>";
        $fields = $this->getFields();
        $lastField = end($fields); // Obtenez le dernier élément du tableau
        foreach ($fields as $index => $field) {
            $isLast = ($field === $lastField);
            $tbody .= "\n\t\t\t\t\t\t\t";
            if (in_array($field, ['content', 'moreDescription', 'additionalInfos'])) {
                $tbody .= "<td>{!! \${$this->entity}->$field !!}</td>";
            } elseif (stripos($field, 'price') !== false) {
                $tbody .= "<td>{{ number_format(\${$this->entity}->$field, 2, ',', ' ') . ' €' }}</td>";
            } elseif ($field === "imageUrl") {
                $tbody .= '<td>
                            <div class="form-group d-flex" id="preview_imageUrl" style="max-width: 100%;">
                                <img src="{{  Str::startsWith($' . $this->entity . '->' . $field . ', \'http\') ? $' . $this->entity . '->' . $field . ' : Storage::url($' . $this->entity . '->' . $field . ') }}"
                                     alt="Prévisualisation de l\'image"
                                     style="max-width: 100px; display: block;">
                            </div>
                        </td>';
            } elseif ($field === "imageUrls") {
                $tbody .= <<<HTML
                        <td>
                            <div class="form-group d-flex" id="preview_imageUrl" style="max-width: 100%;">
                            @foreach ([\${$entityInstance}->$field()[0]] as \$url) 
                                <img src="{{Str::startsWith(\$url, 'http') ? \$url : Storage::url(\$url)}}"
                                    alt="Prévisualisation de l\'image"
                                    style="max-width: 100px; display: block;"
                                    />
                            @endforeach
                        </div>
                    </td>
                    HTML;

            } elseif (Str::startsWith(Str::lower($field), "is")) {
                $tbody .= <<<HTML
                            <td>
                            <div class="form-check form-switch">
                                <input name="$field" id="$field" data-id="{{\${$entityInstance}->id}}" value="true" data-bs-toggle="toggle"  {{ isset(\$${entityInstance}) && \$${entityInstance}->{$field} == 'true' ? 'checked' : '' }} class="form-check-input" type="checkbox" role="switch" />
                            </div>
                        </td>
                        HTML;
            } else {
                $tbody .= "<td>{{ \${$this->entity}->$field }}</td>";
            }
        }

        $tbody .= "\n\t\t\t\t\t\t<td>
                    <a href=\"{{ route('admin.{$entityInstance}.show', ['id' => \${$entityInstance}->id]) }}\" class=\"btn btn-primary btn-sm\">
                        <i class=\"fa-solid fa-eye\"></i>
                    </a>
                    <a href=\"{{ route('admin.{$entityInstance}.edit', ['id' => \${$entityInstance}->id]) }}\" class=\"btn btn-success btn-sm\">
                        <i class=\"fa-solid fa-pen-to-square\"></i>
                    </a>
                    <a href=\"#\" data-id=\"{{ \${$entityInstance}->id }}\" class=\"btn btn-danger btn-sm deleteBtn\">
                        <i class=\"fa-solid fa-trash\"></i>
                    </a>
                </td>\n\t\t\t\t\t\t";
        $tbody .= "</tr>\n\t\t\t\t\t";




        $tbody .= "@endforeach";


        // Générer la vue Show avec un tableau Bootstrap et un entête dynamique
        $content = <<<EOD
                        @extends('admin')

                        @section('styles')
                            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
                        @endsection

                        @section('content')
                        <div >
                        <h3> $EntityNames Details</h3>

                        <div class="d-flex justify-content-end">
                            <div class="dropdown m-1">
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown"
                                    aria-expanded="false">
                                    Column
                                </button>
                                <div id="columnSelector" class="dropdown-menu"> </div>
                            </div>
                            <a href="{{ route('admin.{$entityInstance}.create') }}" class="btn btn-success m-1">

                                    Create {$entityName}

                            </a>
                        </div>
                        <div class="">
                            <div class="card-body">
                            <div class="table-responsive">
                                <table  id="{$entityName}" class="table">
                                    <thead>
                                        <tr>
                                            $thead
                                        </tr>
                                    </thead>
                                    <tbody>
                                        $tbody
                                    </tbody>
                                </table>
                            </div>

                                <!-- Pagination -->
                                <div class="d-flex justify-content-center">
                                    {{ \${$entityNames}->links('pagination::bootstrap-5') }}
                                </div>
                            </div>
                        </div>
                        </div>


                            <!-- Modal -->
                            <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                    <h3 class="modal-title fs-5" id="confirmModalLabel">Delete confirm</h3>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                    ...
                                    </div>
                                    <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary confirmDeleteAction">Delete</button>
                                    </div>
                                </div>
                                </div>
                            </div>
                        @endsection
                        @section('scripts')
                           
                            <script>
                                const checkboxs = document.querySelectorAll('input[type="checkbox"]')

                                checkboxs.forEach((checkbox) => {

                                checkbox.onchange = async (event) => {
                                    const { checked, name, dataset } = event.target;
                                    const { id } = dataset;
                                    console.log({ checked, name, id });
                                    const data = { [name]: checked.toString() };
                                    const csrfToken = document.head.querySelector('meta[name="csrf-token"]').content;
                                    const response = await fetch('/admin/{$this->entityNames}/speed/' + id, {
                                        method: 'PUT',
                                        body: JSON.stringify(data), // Utilisation de JSON.stringify au lieu de JSON.stringfy
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': csrfToken
                                        }
                                    });
                                };
                                })
                                
                                const deleteButtons = document.querySelectorAll('.deleteBtn')
                                deleteButtons.forEach(deleteButton => {
                                    deleteButton.addEventListener('click', (event)=>{
                                        event.preventDefault();
                                        const { id , title } = deleteButton.dataset
                                        const modalBody = document.querySelector('.modal-body')
                                        modalBody.innerHTML = `Are you sure you want to delete this data ?</strong> `
                                        console.log({ id , title });
                                        const modal = new bootstrap.Modal(document.querySelector('#confirmModal'))
                                        modal.show()
                                        const confirmDeleteBtn = document.querySelector('.confirmDeleteAction')

                                        confirmDeleteBtn.addEventListener('click',async ()=>{
                                            const csrfToken = document.head.querySelector('meta[name="csrf-token"]').content;
                                            const response = await fetch('/admin/{$this->entityNames}/delete/'+id , {
                                                method: 'DELETE',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'X-CSRF-TOKEN': csrfToken
                                                }
                                            })

                                            const result = await response.json()

                                            if(result && result.isSuccess){
                                                window.location.href = window.location.href;
                                            }


                                            modal.hide()
                                        })
                                    })

                                });
                                document.addEventListener('DOMContentLoaded', function() {
                                    const tableHeaders = document.querySelectorAll('#{$entityName} th');
                                    const columnSelector = document.getElementById('columnSelector');
                        
                                    tableHeaders.forEach(function(header, index) {
                                        const li = document.createElement('li');
                                        const a = document.createElement('a');
                                        const div = document.createElement('div');
                                        a.className = 'dropdown-item';
                                        div.className = 'form-check form-switch';
                                        const label = document.createElement('label');
                                        const checkbox = document.createElement('input');
                                        checkbox.type = 'checkbox';
                                        checkbox.role="switch"
                                        checkbox.className = 'columnSelector form-check-input';
                                        checkbox.dataset.column = index;
                                        const savedSelection = localStorage.getItem('selectedColumns#{$entityName}');
                                        checkbox.checked = !!!savedSelection; // Sélectionner par défaut
                                        checkbox.addEventListener('change', function() {
                                            const columnIndex = parseInt(checkbox.dataset.column);
                                            toggleColumn(columnIndex, checkbox.checked);
                                            saveSelection();
                                        });
                        
                                        label.appendChild(document.createTextNode(header.textContent));
                                        div.appendChild(label)
                                        div.appendChild(checkbox)
                                        a.appendChild(div);
                                        li.appendChild(a);
                                        columnSelector.appendChild(li);
                        
                                        header.addEventListener('click', function() {
                                            sortTable(index);
                                        });

                                        if (savedSelection) {
                                            const selectedColumns = JSON.parse(savedSelection);
                                            toggleColumn(parseInt(index), selectedColumns.includes(index));
                                        }
                                    });
                        
                        
                                    const checkboxes = document.querySelectorAll('.columnSelector');
                        
                                    checkboxes.forEach(function(checkbox) {
                                        checkbox.addEventListener('change', function() {
                                            const columnIndex = parseInt(checkbox.dataset.column);
                                            toggleColumn(columnIndex, checkbox.checked);
                        
                                            // Sauvegarde la sélection dans le localStorage
                                            saveSelection();
                                        });
                                    });
                        
                                    // Chargement des valeurs sauvegardées dans le localStorage
                                    loadSavedSelection();
                                });
                        
                                function toggleColumn(columnIndex, show) {
                                    const dataTable = document.getElementById('{$entityName}');
                                    const cells = dataTable.querySelectorAll(
                                        `tr td:nth-child(\${columnIndex + 1}), th:nth-child(\${columnIndex + 1})`);
                        
                                    cells.forEach(function(cell) {
                                        if (show) {
                                            cell.style.display = ''; // Affiche la colonne
                                        } else {
                                            cell.style.display = 'none'; // Masque la colonne
                                        }
                                    });
                                }
                        
                                function saveSelection() {
                                    const selectedColumns = Array.from(document.querySelectorAll('.columnSelector'))
                                        .filter(c => c.checked)
                                        .map(c => c.dataset.column);
                                    localStorage.setItem('selectedColumns#{$entityName}', JSON.stringify(selectedColumns));
                                }
                        
                                function loadSavedSelection() {
                                    const savedSelection = localStorage.getItem('selectedColumns#{$entityName}');
                                    if (savedSelection) {
                                        const selectedColumns = JSON.parse(savedSelection);
                                        selectedColumns.forEach(function(columnIndex) {
                                            const checkbox = document.querySelector(`.columnSelector[data-column="\${columnIndex}"]`);
                                            if (checkbox) {
                                                checkbox.checked = true;
                                                toggleColumn(parseInt(columnIndex), true);
                                            }
                                        });
                                    }
                                }
                        
                                function sortTable(columnIndex) {
                                    const table = document.getElementById('{$entityName}');
                                    const rows = Array.from(table.querySelectorAll('tbody tr'));
                        
                                    console.log({rows});
                        
                                    rows.sort((a, b) => {
                                        const cellA = a.querySelectorAll('td')[columnIndex].textContent;
                                        const cellB = b.querySelectorAll('td')[columnIndex].textContent;
                        
                                        return cellA.localeCompare(cellB, undefined, { numeric: true, sensitivity: 'base' });
                                    });
                        
                                    table.querySelector('tbody').innerHTML = '';
                                    rows.forEach(row => table.querySelector('tbody').appendChild(row));
                                }
                            </script>
                        @endsection
                        EOD;

        File::put(resource_path('views/' . $this->entityNames . '/index.blade.php'), $content);
        $this->info('2- Data List template : resources/views/' . $this->entityNames . '/index.blade.php');
    }

    protected function createRoutes()
    {
        $entityName = ucfirst($this->entity);
        $EntityName = ucfirst(Str::singular($this->entity));
        $EntityNames = ucfirst(Str::plural($this->entity));
        $entityNames = Str::plural(Str::lower($this->entity));
        $entityInstance = Str::camel($this->entity);
        $controllerNamespace = 'App\\Http\\Controllers\\'; // Ajoutez votre namespace ici si différent

        $routeContent = <<<EOD
    Route::prefix('admin')->name('admin.')->group(function(){

        //Get $EntityNames datas
        Route::get('/{$entityNames}', '{$controllerNamespace}{$entityName}Controller@index')->name('{$entityInstance}.index');

        //Show $EntityName by Id
        Route::get('/{$entityNames}/show/{id}', '{$controllerNamespace}{$entityName}Controller@show')->name('{$entityInstance}.show');

        //Get $EntityNames by Id
        Route::get('/{$entityNames}/create', '{$controllerNamespace}{$entityName}Controller@create')->name('{$entityInstance}.create');

        //Edit $EntityName by Id
        Route::get('/{$entityNames}/edit/{id}', '{$controllerNamespace}{$entityName}Controller@edit')->name('{$entityInstance}.edit');

        //Save new $EntityName
        Route::post('/{$entityNames}/store', '{$controllerNamespace}{$entityName}Controller@store')->name('{$entityInstance}.store');

        //Update One $EntityName
        Route::put('/{$entityNames}/update/{{$entityInstance}}', '{$controllerNamespace}{$entityName}Controller@update')->name('{$entityInstance}.update');

        //Update One $EntityName Speedly
        Route::put('/{$entityNames}/speed/{{$entityInstance}}', '{$controllerNamespace}{$entityName}Controller@updateSpeed')->name('{$entityInstance}.update.speed');

        //Delete $EntityName
        Route::delete('/{$entityNames}/delete/{{$entityInstance}}', '{$controllerNamespace}{$entityName}Controller@delete')->name('{$entityInstance}.delete');

    });
    EOD;

        $routesFilePath = base_path('routes/web.php');
        $existingRoutes = file_get_contents($routesFilePath);

        // Vérifiez si le contenu des routes existe déjà dans le fichier
        if (strpos($existingRoutes, $routeContent) === false) {
            File::append($routesFilePath, PHP_EOL . $routeContent);
            $this->info('Updated routes/web.php');
        } else {
            $this->info('Routes already exist in routes/web.php');
        }
    }

    protected function generateBase()
    {
        $baseFileName = resource_path('views/admin.blade.php'); // Correction du chemin et de la fonction resource_path()

        $content = <<<HTML
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta http-equiv="X-UA-Compatible" content="ie=edge">
            <meta name="csrf-token" content="{{ csrf_token() }}">
            <title>
                @yield('title')
            </title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
                integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
                @yield('styles')
            <style>
                .espero-soft-admin {
                    background-color: #fafafa;
                }

                .espero-soft-admin .row {
                    height: 100vh;
                }
                .espero-soft-admin a{
                    /* color: inherit; */
                    text-decoration: inherit;
                }
                .espero-soft-admin h2{
                    color: black;
                    text-transform: uppercase;
                }
                .espero-soft-admin nav-item{
                    
                }

                .btn {
                    border-radius: 0;
                }
            </style>
        </head>

        <body>
            <div class="container-fluid espero-soft-admin">
                <div class="row gx-0 gy-0">
                    <nav id="sidebar" class="col-md-2 border-end d-none d-md-block bg-light sidebar">
                        <div class="sidebar-sticky">
                            <h2>Blog</h2>
                            <ul class="nav flex-column">

                            </ul>
                        </div>
                    </nav>

                    <main id="main-content"  class="col-md-10">
                        <h2 class="border-bottom m-0 p-3">Dashboard</h2>
                        <div class="p-3">
                            @yield('content')
                        </div>
                    </main>
                </div>
            </div>
                <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
                <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
                <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js" integrity="sha384-BBtl+eGJRgqQAUMxJ7pMwbEyER4l1g+O15P+16Ep7Q9Q+zqX6gSbd85u4mG4QzX+" crossorigin="anonymous"></script>
                <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
                <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap4.min.js"></script>
            @yield('scripts')

        </body>

        </html>
        HTML;

        if (!file_exists($baseFileName)) {
            // Créer le fichier $baseFileName si nécessaire
            file_put_contents($baseFileName, $content); // Utilisation de file_put_contents pour créer le fichier
            $this->info('Base view file created: ' . $baseFileName);
        }
    }

    protected function addNavItem($text)
    {
        $baseFileName = resource_path('views/admin.blade.php'); // Assurez-vous que le chemin est correct
        $entityNames = Str::singular($text);
        $EntityNames = Str::plural(Str::ucfirst($text));
        $navItem = <<<HTML
    <li class="nav-item">
        <a class="nav-link" href="{{route('admin.{$entityNames}.index')}}">
            $EntityNames
        </a>
    </li>
    HTML;

        // Charge le contenu existant du fichier
        $existingContent = file_get_contents($baseFileName);

        // Vérifie si l'élément de menu existe déjà dans le fichier
        if (strpos($existingContent, $navItem) !== false) {
            $this->info('Navigation item already exists in the base view file: ' . $baseFileName);
            return; // Arrête la fonction si l'élément existe déjà
        }

        // Trouve l'emplacement où insérer le nouvel élément dans le menu de navigation
        $insertPosition = strpos($existingContent, '</ul>');
        if ($insertPosition !== false) {
            // Insère le nouvel élément juste avant la fermeture de la liste ul
            $newContent = substr_replace($existingContent, $navItem, $insertPosition, 0);

            // Écrit le contenu modifié dans le fichier
            file_put_contents($baseFileName, $newContent);
            $this->info('Navigation item added to the base view file: ' . $baseFileName);
        } else {
            $this->error('Could not find the appropriate position to insert the navigation item.');
        }
    }


    protected function getFields()
    {
        $migrationFileName = database_path('migrations') . "/" . $this->getMigrationFileName();

        $fields = [];
        if (file_exists($migrationFileName)) {
            // Extraire les champs de la migration
            $migrationContent = file_get_contents($migrationFileName);
            preg_match_all('/\$table->([\w]+)\(([^)]+)/', $migrationContent, $matches);

            foreach ($matches[2] as $match) {
                $fieldData = explode(',', $match);
                $fieldName = trim($fieldData[0], "'\"");

                // Vérifier si le champ appartient au modèle App\Models
                if (
                    strpos($fieldName, '\\App\\Models\\') === false &&
                    strpos($fieldName, '[') === false
                ) {
                    $fields[] = $fieldName;
                }
            }
        }
        return $fields;
    }
    protected function getMigrationFileName()
    {
        $migrationsPath = database_path('migrations');
        $entityMigration = '';

        $files = scandir($migrationsPath);

        foreach ($files as $file) {
            if (strpos($file, '_create_' . Str::snake(Str::plural($this->entity)) . '_table.php') !== false) {
                $entityMigration = $file;
                break;
            }
        }

        return $entityMigration;
    }


}
