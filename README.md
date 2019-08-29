## SilverStripe Gatsby module

This module provides a graphql API that is tailored to provide data to a
[Gatsby](https://gatsbyjs.com) static build. This allows your SilverStripe CMS
installation to become a headless data provider for your Gatsby static site
generation.

## Installation

```
$ composer require silverstripe/silverstripe-gatsby
```

## Requirements

Your Gatsby project must have the [gatsby-source-silverstripe](https://github.com/silverstripe/gatsby-source-silverstripe) plugin installed.

`$ npm install --save gatsby-source-silverstripe`

It is also recommended that you use the [silverstripe-gatsby-helpers](https://github.com/unclecheese/silverstripe-gatsby-helpers) library installed.

`$ npm install --save silverstripe-gatsby-helpers`

## How to use the API

The API has its own endpoint, separate from the SilverStripe admin GraphQL 
server or any GraphQL you may be using on your frontend.

`http://mysite.com/__gatsby/graphql`

### Querying the data

The query itself is a bit awkward, but in general, the only client accessing
this API should be Gatsby itself, and therefore it doesn't have a huge obligation
to be developer-friendly.

```
query Sync($Limit:Int!, $Token:String, $Since:String) {
  sync {
    results(limit: $Limit, offsetToken: $Token, since: $Since) {
      offsetToken # Use this on subsequent requests to chunk results
      nodes {
        id
        parentUUID
        uuid
        created
        lastEdited
        className
        ancestry
        contentFields # All custom fields are flattened into a JSON string
        link # DataObjects are encouraged to provide Link() methods if they appear as pages
        relations {
          type # HAS_ONE, HAS_MANY, etc
          name
          ownerType
          records {
            className
            id
            uuid
          }
        }
      }
    }
  }
}`
```

### Arguments
* **limit**: Limit the number of records that come back for each query. On a large site, this is critical. Default value: `1000`.
* **offsetToken**: If your previous request provided you with a `offsetToken`, pass it back in here to pick up where the previous result set left off.
* **since**: Essential for incremental/preview builds in Gatsby. Provide a timestamp as a reference to get only records that have changed since then.

## Controlling what dataobjects are exposed

Obviously, you don't want to expose every single dataobject in the application.
Internals like `LoginAttempt` and `ChangeSet` are not remotely useful to a Gatsby
build, where content is the focus.

You can whitelist and blacklist classes, using wildcarding.

```yml
SilverStripe\Gatsby\GraphQL\Types\SyncResultTypeCreator:
  included_dataobjects:
    myapp: 'MyCompany\MyApp\*'
  excluded_dataobjects:
    security: 'SilverStripe\Security\*'
    versioned: 'SilverStripe\Versioned\*'
    shortcodes: 'SilverStripe\Assets\Shortcodes\*'
    siteTreeLink: 'SilverStripe\CMS\Model\SiteTreeLink'
```

If both a whitelist and blacklist are provided, the blacklist has the final say.

If no whitelist is provided, all dataobjects are in the query by default, until blacklisted.

If no whitelist and no blacklist are provided, don't do that.

## Monolithic typing

Due to the complex inheritance patterns in SilverStripe, creating a properly typed
API using unions and interfaces would be a lot of effort and may actually make the developer
experience worse in the end. For now, all types are `DataObject`, and the Gatsby
source plugin is able to pseudo-type the `customFields` blob by namespacing each
unserialised field in class-specific subfields.

```
query {
	allDataObjects {
		# core fields here
		id
		link
		lastEdited
		SilverStripeSiteTree {
			# fields specific to this class are grouped appropriately
			title
			menuTitle
			content
		}
		SilverStripeBlog {
			featuredImage {
				link
			}
			Children {
				link
				# relationships still have to nest their fields
				SilverStripeBlogPost {
					categories {
						title
					}
				}
			}
		}
	}
}
```
## Token-based authentication (for draft content)

Coming soon.