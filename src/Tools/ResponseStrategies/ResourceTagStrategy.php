<?php


namespace Mpociot\ApiDoc\Tools\ResponseStrategies;


use Illuminate\Routing\Route;
use Mpociot\Reflection\DocBlock\Tag;
use Illuminate\Support\Collection;

class ResourceTagStrategy
{
    /**
     * @param Route $route
     * @param array $tags
     * @param array $routeProps
     *
     * @return array|null
     */
    public function __invoke(Route $route, array $tags, array $routeProps)
    {
        return $this->getResourceResponse($tags);
    }

    /**
     * Get a response from the resource tags.
     *
     * @param array $tags
     *
     * @return array|null
     */
    protected function getResourceResponse(array $tags)
    {
        try {
            if (empty($resourceTag = $this->getResourceTag($tags))) {
                return;
            }

            $resource = $this->getResourceClass($resourceTag);
            $model = $this->getClassToBeTransformed($tags);
            $modelInstance = $this->instantiateResourceModel($model);

            $resource = (strtolower($resourceTag->getName()) == 'resourcecollection')
                ? $resource::collection(new Collection([$modelInstance, $modelInstance]))
                : new $resource($modelInstance);

            return [response($resource)];
        } catch (\Exception $e) {
            return;
        }
    }

    /**
     * @param Tag $tag
     *
     * @return string|null
     */
    private function getresourceClass($tag)
    {
        return $tag->getContent();
    }

    /**
     * @param array $tags
     *
     * @return null|string
     * @throws \Exception
     */
    private function getClassToBeTransformed(array $tags)
    {
        $modelTag = array_first(array_filter($tags, function ($tag) {
            return ($tag instanceof Tag) && strtolower($tag->getName()) == 'resourcemodel';
        }));

        $type = null;
        if ($modelTag) {
            $type = $modelTag->getContent();
        } else {
            throw new \Exception('resourceModel needs to be provided');
        }

        return $type;
    }

    /**
     * @param string $type
     *
     * @return mixed
     * @throws \Exception
     */
    protected function instantiateResourceModel(string $type)
    {
        try {
            // try Eloquent model factory
            return factory($type)->make();
        } catch (\Exception $e) {
            $instance = new $type;
            if ($instance instanceof \Illuminate\Database\Eloquent\Model) {
                try {
                    // we can't use a factory but can try to get one from the database
                    $firstInstance = $type::first();
                    if ($firstInstance) {
                        return $firstInstance;
                    }
                } catch (\Exception $e) {
                    throw $e;
                    // okay, we'll stick with `new`
                }
            }
        }

        return $instance;
    }

    /**
     * @param array $tags
     *
     * @return Tag|null
     */
    private function getResourceTag(array $tags)
    {
        $resourceTags = array_values(
            array_filter($tags, function ($tag) {
                return ($tag instanceof Tag) && in_array(strtolower($tag->getName()), ['resource', 'resourcecollection']);
            })
        );

        return array_first($resourceTags);
    }
}