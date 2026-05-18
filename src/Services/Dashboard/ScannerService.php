<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services\Dashboard;

use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;
use EstebanSmolak19\CrudServiceGenerator\Attributes\IsService;

class ScannerService
{
    public function __construct() {}

    /**
     * Récupère tous les contrôleurs de l'application hôte avec leurs méthodes et services liés
     */
    public function getControllers(): array
    {
        $controllersPath = app_path('Http/Controllers');

        // Si le dossier n'existe pas
        if (!File::isDirectory($controllersPath)) {
            return [];
        }

        // On récupère tous les fichiers .php dans ce dossier et ses sous-dossiers
        $files = File::allFiles($controllersPath);
        $detectedControllers = [];

        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file);

            // On vérifie que la classe existe et qu'on peut l'inspecter
            if (class_exists($className)) {
                $reflection = new ReflectionClass($className);

                // On ignore la classe de base "Controller.php" de Laravel et les classes abstraites
                if ($reflection->isAbstract() || $className === 'App\Http\Controllers\Controller') {
                    continue;
                }

                // On extrait le service injecté qui porte l'attribut #[IsService]
                $injectedService = $this->getInjectedService($reflection);

                // On extrait les informations du contrôleur
                $detectedControllers[] = [
                    'name'             => $className,                               // Nom complet (Namespace)
                    'short_name'       => $reflection->getShortName(),              // Juste le nom de la classe
                    'injected_service' => $injectedService,                         // Le service détecté ou null
                    'methods'          => $this->getControllerMethods($reflection), // Ses fonctions publiques
                ];
            }
        }

        return $detectedControllers;
    }

    /**
     * Retourne uniquement la carte associative (mapping) [ NomDuController => NomDuService ]
     */
    public function getControllerServiceMapping(): array
    {
        $allControllers = $this->getControllers();
        $mapping = [];

        foreach ($allControllers as $controller) {
            if (!empty($controller['injected_service'])) {
                $mapping[$controller['name']] = $controller['injected_service']['name'];
            }
        }

        return $mapping;
    }

    /**
     * Analyse le constructeur pour trouver la classe injectée portant l'attribut #[IsService]
     */
    private function getInjectedService(ReflectionClass $reflection): ?array
    {
        $constructor = $reflection->getConstructor();

        // S'il n'y a pas de constructeur, aucun service n'est injecté
        if (!$constructor) {
            return null;
        }

        // On inspecte chaque paramètre du constructeur
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            // On ignore les types primitifs (string, int...) ou l'absence de type
            if (!$type || $type->isBuiltin()) {
                continue;
            }

            $parameterClassName = $type->getName();

            if (class_exists($parameterClassName)) {
                $serviceReflection = new ReflectionClass($parameterClassName);

                // On cherche l'attribut #[IsService] sur la classe du paramètre
                $attributes = $serviceReflection->getAttributes(IsService::class);

                // Si trouvé, on extrait les informations du service
                if (!empty($attributes)) {
                    return [
                        'name'       => $parameterClassName,
                        'short_name' => $serviceReflection->getShortName(),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Convertit le chemin d'un fichier physique en Namespace PHP complet
     */
    private function getClassNameFromFile($file): string
    {
        $relativePath = $file->getRelativePathname();

        // On retire l'extension .php
        $classWithoutExtension = str_replace('.php', '', $relativePath);

        // On remplace les slashs par des antislashs pour le namespace
        $subNamespace = str_replace('/', '\\', $classWithoutExtension);

        return 'App\\Http\\Controllers\\' . $subNamespace;
    }

    /**
     * Extrait uniquement les méthodes publiques créées par le développeur
     */
    private function getControllerMethods(ReflectionClass $reflection): array
    {
        $methods = [];

        // On ne prend que les méthodes PUBLIQUES du contrôleur
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {

            // On ignore les méthodes statiques et magiques comme __construct
            if ($method->isStatic() || str_starts_with($method->name, '__')) {
                continue;
            }

            // On ignore les méthodes héritées de la classe mère de Laravel
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $methods[] = [
                'name' => $method->name,
            ];
        }

        return $methods;
    }
}