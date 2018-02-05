<?php

namespace Midnite81\Salesforce\Model;

use App\Services\Auth;
use Midnite81\Salesforce\Services\Client;
use Exception;
use Midnite81\Salesforce\Builder\QueryBuilder;
use Midnite81\Salesforce\Exceptions\ActiveRecordNotSetException;
use Midnite81\Salesforce\Exceptions\ConnectionNotSetException;

abstract class Model
{
    /**
     * The URL for the Salesforce Object
     *
     * @var string
     */
    protected $objectUrl;

    /**
     * The attributes which get filled on the model
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Primary Key Id
     *
     * @var string
     */
    protected $primaryKey;

    /**
     * The base url for the salesforce instance
     *
     * @var \Illuminate\Config\Repository|mixed
     */
    protected $baseUrl;


    public function __construct($attributes = [])
    {
        $this->fillAttributes($attributes);
        $this->baseUrl = config('salesforce.devinstance');
    }

    /**
     * Create new instance of class
     *
     * @return static
     */
    public static function newInstance()
    {
        return new static();
    }

    /**
     * Find the Record
     *
     * @param $id
     * @return mixed|string
     */
    public static function find($id)
    {
        $instance = static::newInstance();

        try {
            $url = $instance->getConnection($id);
            $client = new Client();
            $response = $client->request($url, null, Auth::authorisationHeader());
        } catch (\Exception $e) {
            return 'Could not retrieve data: ' . $e->getMessage() . $e->getTraceAsString();
        }

        $instance->fillAttributes($response->getBody()->getContents());

        return $instance;
    }

    /**
     * Create the object
     *
     * @param array $data
     * @return mixed
     */
    public static function create(array $data = [])
    {
        $instance = static::newInstance();

        try {
            $url = $instance->getConnection();
            $client = new Client();
            $response = $client->request($url, $data, Auth::authorisationHeader());
        } catch (\Exception $e) {
            $instance->error($e);
        }

        $data = json_decode($response->getBody()->getContents());

        return static::find($data->id);
    }

    /**
     * @param QueryBuilder $query
     * @param bool         $first
     * @return \Illuminate\Support\Collection
     */
    public function executeQuery(QueryBuilder $query, $first = false)
    {
        try {
            $url = $this->getQueryConnection(http_build_query([
                'q' => $query->toSql()
            ]));
            $client = new Client();
            $response = $client->request($url, null, Auth::authorisationHeader());
        } catch (\Exception $e) {
            $this->error($e);
        }

        if ($first) {
            $data = json_decode($response->getBody()->getContents());
            if (! empty($data->records[0])) {
                return collect($data->records[0]);
            }
            return null;
        }

        return collect(json_decode($response->getBody()->getContents()));
    }

    /**
     * @param string $query
     * @param bool   $first
     * @return \Illuminate\Support\Collection
     */
    public function executeQueryRaw(string $query, $first = false)
    {
        try {
            $url = $this->getQueryConnection(http_build_query([
                'q' => $query
            ]));
            $client = new Client();
            $response = $client->request($url, null, Auth::authorisationHeader());
        } catch (\Exception $e) {
            $this->error($e);
        }

        if ($first) {
            $data = json_decode($response->getBody()->getContents());
            if (! empty($data->records[0])) {
                return collect($data->records[0]);
            }
            return null;
        }

        return collect(json_decode($response->getBody()->getContents()));
    }

    /**
     * Get all
     */
    public static function get()
    {
        $instance = static::newInstance();

        try {
            $url = $instance->getQueryConnection();
            $client = new Client();
            $response = $client->request($url, null, Auth::authorisationHeader());
        } catch (\Exception $e) {
            $instance->error($e);
        }

        return collect(json_decode($response->getBody()->getContents()));
    }

    /**
     * Update Object
     *
     * @param array $data
     * @return mixed
     * @throws ConnectionNotSetException
     * @throws ActiveRecordNotSetException
     */
    public function update(array $data = [])
    {
        if (! empty($this->attributes[$this->primaryKey()])) {
            $url = $this->getConnection($this->attributes[$this->primaryKey()]);

            $client = new Client();
            try {
                $response = $client->patch($url, $data, Auth::authorisationHeader());
            } catch (\Exception $e) {
                return $this->error($e);
            }

            return $this->jsonDecodeBodyResponse($response);
        }

        throw new ActiveRecordNotSetException('Active Record is not set');
    }

    /**
     * Find Record where it matches the attributes
     *
     * @param array $attributes
     * @return mixed
     * @throws \Illuminate\Container\EntryNotFoundException
     * @throws ConnectionNotSetException
     */
    public static function findWhere(array $attributes)
    {
        $client = new Client();

        $instance = static::newInstance();

        $where = [];

        if (! empty($attributes)) {
            foreach($attributes as $key=>$attribute) {
                $where[] = $key . " = '" . $attribute . "'";
            }
        }

        $url = $instance->getQueryConnection(http_build_query([
                'q' => 'SELECT Id FROM ' . $instance->getObjectName() . ' WHERE ' . implode(' AND ', $where)
            ]));

        $response = $client->request($url, null, Auth::authorisationHeader());

        return $response->getBody()->getContents();

    }

    /**
     * Fill Attributes
     *
     * @param $data
     */
    protected function fillAttributes($data)
    {

        if (! empty($data) && json_decode($data, TRUE)) {
            $data = json_decode($data, TRUE);
        }

        if (! empty($data) && ! empty($this->attributes)) {
            $this->attributes = array_merge($this->attributes, $data);
        } else {
            $this->attributes = $data;
        }

    }


    /**
     * Get Connection String
     *
     * @param string|null $path
     * @return string
     * @throws ConnectionNotSetException
     */
    public function getConnection(string $path = '')
    {
        if (! empty($this->baseUrl) && ! empty($this->objectUrl)) {
            return (empty($path)) ? $this->baseUrl . $this->objectUrl : $this->baseUrl . $this->objectUrl . '/' . $path;
        }

        throw new ConnectionNotSetException('The objectUrl has not been set on the class');
    }

    /**
     * Query Connection
     *
     * @param string $query
     * @return string
     * @throws ConnectionNotSetException
     */
    public function getQueryConnection(string $query = '')
    {
        if (! empty($this->baseUrl) && ! empty($this->objectUrl)) {
            return (empty($query)) ? $this->baseUrl . '/services/data/v20.0/query' : $this->baseUrl . '/services/data/v20.0/query?' . $query;
        }

        throw new ConnectionNotSetException('The objectUrl has not been set on the class');
    }

    /**
     * Get Object Name
     *
     * @return string
     */
    public function getObjectName()
    {
        return basename($this->objectUrl);
    }

    /**
     * Get primary key
     *
     * @return bool|null
     */
    public function getId()
    {
        return (! empty($this->attributes[$this->primaryKey()])) ?? null;
    }

    /**
     * Get Primary Key Name
     */
    public function primaryKey()
    {
        return $this->primaryKey ?? 'Id';
    }

    /**
     * Get all attributes
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * ToString
     *
     * @return bool|null
     */
    public function __toString()
    {
        return json_encode($this->attributes);
    }

    /**
     * Get the definition of the object
     *
     * @return string
     */
    public function describe()
    {
        $describeUrl = config('salesforce.instance') . '/services/data/v20.0/sobjects/' . $this->getObjectName() . '/describe';

        try {
            $client = new Client();
            $response = $client->request($describeUrl, null, Auth::authorisationHeader());
        } catch (\Exception $e) {
            return 'Could not retrieve data: ' . $e->getMessage() . $e->getTraceAsString();

        }

        return $response->getBody()->getContents();
    }

    /**
     * Error Handling
     *
     * @param Exception $e
     */
    protected function error(Exception $e)
    {
        // TODO: UPDATE
        dd($e->getMessage(), $e->getTraceAsString(), __LINE__);
    }

    /**
     * Decode Body Response
     *
     * @param $response
     * @return mixed
     */
    protected function jsonDecodeBodyResponse($response)
    {
        return json_decode($response->getBody()->getContents());
    }

    /**
     * Magic Get method
     *
     * @param $name
     * @return bool|mixed
     */
    public function __get($name)
    {

        if (! empty($this->attributes[$name])) {
            return $this->attributes[$name];
        }

        if (! empty($this->attributes[strtolower($name)])) {
            return $this->attributes[strtolower($name)];
        }

        return false;
    }

    /**
     * Magic Method __call
     */
    public function __call($method, $arguments)
    {
        return (new QueryBuilder($this))->$method(...$arguments);
    }

}