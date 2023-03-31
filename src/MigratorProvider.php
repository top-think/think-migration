<?

namespace think\migration;

use think\helper\Arr;

class MigratorProvider
{

    /**
     * The paths to all of the migration files.
     *
     * @var array
     */
    protected $paths = [];

    /**
     * Register a custom migration path.
     *
     * @param  string $path
     * @return void
     */
    public function path($path)
    {
        $this->paths = array_unique(array_merge($this->paths, [$path]));
    }

    /**
     * Get all of the custom migration paths.
     *
     * @return array
     */
    public function paths()
    {
        return $this->paths;
    }

    /**
     * Get all of the migration files in a given path.
     *
     * @param  string|array  $paths
     * @return array
     */
    public function getMigrationFiles($paths)
    {
        return array_keys(
            array_unique(
                collect(Arr::collapse(
                    collect($paths)->map(function ($path) {
                        return str_ends_with($path, '.php') ? [$path] : glob($path . DIRECTORY_SEPARATOR . '*.php', defined('GLOB_BRACE') ? GLOB_BRACE : 0);
                    })
                ))->filter()->flip()->each(function ($key, $file) {
                    return $this->getMigrationName($file);
                })->sort()->all()
            )
        );
    }

    /**
     * Get the name of the migration.
     *
     * @param  string  $path
     * @return string
     */
    public function getMigrationName($path)
    {
        return str_replace('.php', '', basename($path));
    }
}
