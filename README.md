# Tebex Tech Test

## Submission

Please download/clone this repository and submit your final code by emailing a .zip or by sharing a link to your own repository on GitHub/GitLab. Please avoid forking this repository through GitHub.

## Task

### Part 1
Refactor the LookupController in this codebase. In particular, consider how Composition, Inheritance and Contracts can refactor this code in a clean, maintainable way.
Ensure that the final code follows PSR-12 standards (hint, some of it currently doesn't), and structure the code in a way that shows your knowledge of:

- SOLID principles
- OOP
- Contracts (Interfaces)
- Dependency Injection (In Laravel this also includes use of the Service Container)


### Part 2
Write some unit or feature tests for the code you've written. You do not need to have complete test coverage we just want to see that you have some experience in writing automated tests!

### Bonus Points

- Due to rate limits enforced by the underlying services, consider how data can be cached or persisted so that we're not having to call the underlying service every time
- Implement some 'defensive programming' (consider how and why the application might fail and implement appropriate precautions)
- Consider how error/fail states should be communicated back to the user

## Example Requests and expected Results:
(Note: This assumes the code is running on http://localhost:8000) - e.g. having been started using the built-in php server:

`php -S localhost:8000 -t public`

http://localhost:8000/lookup?type=xbl&username=tebex
```json
{"username":"Tebex","id":"2533274844413377","avatar":"https:\/\/avatar-ssl.xboxlive.com\/avatar\/2533274844413377\/avatarpic-l.png"}
```

http://localhost:8000/lookup?type=xbl&id=2533274884045330
```json
{"username":"d34dmanwalkin","id":"2533274884045330","avatar":"https:\/\/avatar-ssl.xboxlive.com\/avatar\/2533274884045330\/avatarpic-l.png"}
```

http://localhost:8000/lookup?type=steam&username=test
Should return an error "Steam only supports IDs"

http://localhost:8000/lookup?type=steam&id=76561198806141009
```json
{"username":"Tebex","id":"76561198806141009","avatar":"https:\/\/steamcdn-a.akamaihd.net\/steamcommunity\/public\/images\/avatars\/c8\/c86f94b0515600e8f6ff869d13394e05cfa0cd6a.jpg"}
```

http://localhost:8000/lookup?type=minecraft&id=d8d5a9237b2043d8883b1150148d6955
```json
{"username":"Test","id":"d8d5a9237b2043d8883b1150148d6955","avatar":"https:\/\/crafatar.com\/avatarsd8d5a9237b2043d8883b1150148d6955"}
```

http://localhost:8000/lookup?type=minecraft&username=Notch
```json
{"username":"Notch","id":"069a79f444e94726a5befca90e38aaf5","avatar":"https:\/\/crafatar.com\/avatars069a79f444e94726a5befca90e38aaf5"}
```

## My Approach

I started with a fresh install of Laravel 12 (habit) and Sail (running Redis locally) and then copied across the `LookupController`.

I switched the endpoint from web to api as it is a stateless, public API endpoint, so we don't have to worry about sessions. This also has the benefit of making it very easy to scale horizontally.

When I looked at the separate external services, I decided that the Strategy pattern would be a good fit, this gives several benefits:
- The logic for each services can be isolated within it's own strategy class.
- Satisfies the Open/Closed principle: If for example we wanted to add a new service we could do it by adding a new strategy without impacting the existing ones.

Initially I created 3 strategies (one for each named service: Steam, XBL and Minecraft). This however was not suitable, as after digging deeper into the Minecraft APIs, I discovered they behaved quite differently so I ended up with 4 Strategies (which all satisfy the `ProfileSourceInterface`):
- `ProfileSourceMinecraftId`
- `ProfileSourceMinecraftUsername`
- `ProfileSourceSteam`
- `ProfileSourceXbl`

Each strategy is responsible for three things, validating the parameters for the external request, generating a cache key and fetching the profile from the external service.

As the external requests are contained within the strategy we can now easily introduce our own per service rate limits (see `ProfileSourceSteam` for an example). This can be beneficial if we don't want to rely on the external service rate limiter (we may have multiple endpoints that rely on that service and not want to use up the bandwidth on this particular lookup operation).

The controller method uses a form request (`ProfileLookupRequest`) to validate the type, validation of the other params is handled within each strategy (as they are really params for the external request).

The type of strategy to use is determined based upon the request data (e.g. if the `type` is `minecraft` and there is an `id`, use `ProfileSourceMinecraftId`). This logic is contained within the `ProfileSourceEnum`.


### Services

#### ProfileService

`ProfileService` is the context class for the strategies, it is responsible for checking the cache and then fetching the profile data via the chosen strategy class. At the moment, profile data is cached for 1 day and there is no way to clear the cache, this may or may not be important depending on business requirements. There are a few options available:
- Automatically refresh the cache after a certain number of requests.
- Provide a secured endpoint for elevated users to clear a cached record.
- Perhaps a day is too long to cache, depending on how the api is used we could reduce this to a number of hours.

#### ExternalRequestService

`ExternalRequestService` is a facade class which wraps the Http Laravel facade the purpose of this class is to cut down on duplication across the Strategies (they all perform the same request with a timeout and retry mechanism). Along with this there is the `ExternalRequestFailedException` which is designed to store the http status code of the external request so that the Controller action can behave correctly.

I am using the Service container to Inject `ExternalRequestService` into the individual strategies, I did debate using an interface for DI in order to make testing easier but as it is relying on the Http facade, I was able to mock this directly in my tests. If it were to become more complex then creating and using an interface would be worth thinking about.

### Endpoint

#### GET /api/lookup

Search a range of sources for a player's profile. The endpoint has a fair usage of 500 requests per minute

##### URL Parameters

- **type=** - Specify the external source of the profile lookup [steam|xbl|minecraft]
- **id=** - The id of the user profile (should be excluded if the username is provided)
- **username=** - The username of the user profile (should be excluded if the id is provided)

##### Example Success Response

```json
{
    "id":  "123",
    "username": "john.smith",
    "avatar": "https://example.com/avatar.png"
}
```

##### Example Validation Response

```json
{
    "message":"The id field is required.",
    "errors": {
        "id": ["The id field is required."]
    }
}
```

##### Example Error Response

```json
{
    "error": {
        "message": "Unable to find profile"
    }
}
```

##### Response Codes

- **200** - Success
- **400** - Client error response from external service
- **404** - Profile not found
- **422** - URL parameters invalid
- **429** - Too many requests to external service
- **500** - Internal serer error
- **502** - Server error response from external service

### Steps to Test Locally

1. `composer install`
2. `cp .env.example .env`
3. `vendor/bin/sail up -d`
4. `vendor/bin/sail artisan key:generate`
6. `curl -H "Accept:application/json" http://localhost/api/lookup?type=xbl&username=tebex`
