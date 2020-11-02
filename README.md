# PROXY SWITCHER
A component for making HTTP requests using proxies. Allows for easy switching between different proxy servers.
## Installation
This component requires php >= 7.4. To install it, you can use composer:
```
composer require unique/proxyswitcher
```
## Usage
### No proxies
You can use this component with no proxies.

```php
<?php
    $transport = new \unique\proxyswitcher\Transport( [
        // any public attributes for the component...            
    ] );
```

### Single Proxy
Or you can specify a single proxy to be used for all requests.
```php
<?php
    $transport = new \unique\proxyswitcher\Transport();
    $transport->setProxyList( 
        new \unique\proxyswitcher\SingleProxyList( [
            'username' => 'user', 
            'password' => 'pass', 
            'address' => 'my.proxy.com' 
        ] ) 
    );
```

### Multiple Proxies and switching between them
Whenever you need to have a list of available proxies and switch between them either after a certain number of requests or when a proxy is blocked/not working, you can use `ArrayProxyList`.

```php
<?php
    $transport = new \unique\proxyswitcher\Transport();
    $transport->setProxyList( 
        new \unique\proxyswitcher\ArrayProxyList( [
            'username' => 'user', 
            'password' => 'pass', 
            'transports' => [
                'my1.proxy.com:80',
                'my2.proxy.com:80',
                'my3.proxy.com:80',
            ] 
        ] ) 
    );
```

When you are using `ArrayProxyList`, you can use `Transport::$switch_transport_min` and `Transport::$switch_transport_max` to specify the number of requests that needs to be made before a forced proxy change will be made. Proxies will be chosen in the order they were provided. If a proxy fails, it will be marked as failed and a switch will be made to the next one. If a proxy fails `ArrayProxyList::$max_transport_fails` times (default 3) it will be marked as invalid and will not be retried.

You can also specify `Transport::$next_timeout_min` and `Transport::$next_timeout_max` to randomize a number of requests after which a timeout will be made. The length of timeout can be specified by using `Transport::$sleep_time_min` and `Transport::$sleep_time_max` which sets a minimum and maximum of seconds to timeout.


### Making requests
Under the hood the component uses GuzzleHttp for making requests, so you can pass any options that you normally would as a third parameter.

However, a few options: `connect_timeout` and `proxy` are set automatically based on the attributes of Transport object.

Also, `Cookie` header is taken from `Transport::$cookie`

```php
    // Make a GET request:
    $response = $transport->request( 
        \unique\proxyswitcher\Transport::REQUEST_GET,
        'https://www.google.com?query=hello+world'
    );
    
    // ...or a POST request:
    $response = $transport->request( 
        \unique\proxyswitcher\Transport::REQUEST_POST,
        'https://www.google.com',
        [
            'form_params' => [
                'query' => 'hello world!'
            ]           
        ]   
    );
```

<p>You can read more about making requests using GuzzleHttp, here: <a href="https://docs.guzzlephp.org/en/stable/overview.html">docs.guzzlephp.org</a></p>

### Creating your own proxy list component
If you need to do more logic than some basic switching, you can write your own ProxyList class by extending `AbstractProxyList`. This can be helpful if you have the list in a DB and want to keep track on the servers.

## Documentation
### Transport attributes
#### `int $next_timeout_min = 2000` and `int $next_timeout_max = 5000`
After a random amount (in the range of min/max) of requests a timeout will be made.
In order to turn this off, set `$sleep_time_min` and `$sleep_time_max` to zero.
#### `int $sleep_time_min = 2 * 60` and `int $sleep_time_max = 5 * 60`
The amount of seconds to sleep after a timeout has been reached.
The real amount of seconds to sleep will be randomized in this range.
Can be set to zero, if no such timeout needs to be made.
#### `int $switch_transport_min = 400` and `int $switch_transport_max = 800`
Minimum and maximum requests between a proxy switch.
If both set to null, the proxy will only be switched once it fails.
#### `int|null $max_proxies_in_a_row = null`
Specifies how many proxies can be tried during a single request, before giving up and throwing an Exception.
This can be used as a safety measure, so that in stead of endlessly going through all the proxies specified, assume that there is something
wrong after this many requests fail. (Like maybe internet connection failure, bad address, etc..)
#### `int $connect_timeout = 1`
Default connection timeout for each request.
Can be overriden by passing options to `Transport::request()` method.
#### `int $timeout_after_request = 1`
Specifies the amount of seconds to wait after each successful request, in order to prevent flooding.
#### `string $cookie = ''`
A cookie string to use for requests.
#### `SingleProxyList|ArrayProxyList|array|null $proxy_list`
If specified, object will be used to control proxy switching.
You can pass an `array` only during contruction of the `Transport` object, for `proxy_list` object to be constructed automatically.
In this case, `array` needs to contain a `['class']` key.

### Transport methods
#### `setProxyList( AbstractProxyList $proxy_list )`
Sets the provided proxy list.

#### `getProxyList(): AbstractProxyList`
Returns the assigned proxy list object.

#### `request( string $method, $url, array $options = [] ): ResponseInterface`
Makes a request to the url, using the provided request method GET/POST.
If a `proxy_list` object was provided during the construction of the object or using `setProxyList()` method, proxy switching logic will be
applied accordingly.
- `string $method` - Either "GET" or "POST".
- `string $url` - A url of the request.
- `array $options = []` - Options for `GuzzleHttp\Client::request()`. <p>You can read more about making requests using GuzzleHttp, here: <a href="https://docs.guzzlephp.org/en/stable/overview.html">docs.guzzlephp.org</a></p>

#### `static getInstance( $config = [] ): Transport`
Returns a static instance of the Transport class.
- `array $config = []` - Any public attribute value of the `Transport` class. Only initialized the first time it is called. (Probably should rework this...)

#### `setLogger( \Closure $logger )`
Sets a logger function for transport and proxy_list.
A logger function will receive a single string parameter with a text that needs logging.
- `\Closure $logger` - Will receive two parameters: `( string $text, bool $is_error )`

#### `on( string $event, $callback )`
Sets a new handler for the specified event type.
- `string $event` - Event name, one of the `Transport` constants: `EVENT_AFTER_RESPONSE` or `EVENT_TOO_MANY_REQUESTS`.
- `\Closure|array $callback` - Handler of the event. Will receive one parameter: `( EventObjectInterface $event )`

#### `trigger( string $event_name, EventObjectInterface $event )`
Triggers the specified event.
The first assigned handler will be called first. If it does not set `EventObjectInterface::setHandled()` the second handler will be called
and so on, until all the handlers have been called or `setHandled( true )` has been set.
- `string $event_name` - Event name, one of the `Transport` constants: `EVENT_AFTER_RESPONSE` or `EVENT_TOO_MANY_REQUESTS`.
- `EventObjectInterface $event` - Event object

#### `off( string $event, $callback = null )`
Removes an event handler from the object.
If no handler is provided all handlers will be removed.
- `string $event` - Event name, one of the `Transport` constants: `EVENT_AFTER_RESPONSE` or `EVENT_TOO_MANY_REQUESTS`.
- `\Closure|array|null $callback` - Handler of the event that was previously assigned using `on()`.

### Using events
This component has a very simple event system with two defined events.
- `AfterResponseEvent` - triggered after a successful request.
- `TooManyRequestsEvent` - triggered after a HTTP 429 response is received.

You can subscribe to an event by calling `Transport::on()` method. An event like `TooManyRequestsEvent` can be set as handled using `setHandled( true )` on the passed Event object, in which case the current proxy will not be failed and the component will try to continue using it.

## License

This project is licensed under the terms of the MIT license.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.