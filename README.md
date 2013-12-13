# JSON Mobile
Contributors: Yuri Victor

Tags: json, api

Requires at least: 3.7.1

Tested up to: 3.7.1

Stable tag: 0.1.0

License: GPLv2 or later

License URI: http://www.gnu.org/licenses/gpl-2.0.html


## Description

A WordPress plugin that creates a json response endpoint for each post. Created for the mobile applications team.

Can be used by appending `?json-mobile` to any WordPress post.

Example:

`http://www.washingtonpost.com/blogs/wonkblog/wp/2013/08/16/dear-dylan-the-wonkblog-advice-column-for-everything/?json-mobile`

### JSON fields

Default fields
* **id** *int* the current post ID
* **title** *string* the current post title
* **author** *string* the current post author(s)
* **published** *string* the original post published date in UTC format
* **lmt** *string* the last time the post was modified in UTC format
* **lead_image** *string* the designated lead image url, or first image, in the post
* **items** *array* each paragraph broken into [individual types](#supported)

Default fields example
```
{
    "id": 88,
	"title": "This is the hard part: Getting that Syria plan through the United Nations",
	"author": "Yuri Victor",
	"published": "2013-09-18T14:25:54+00:00",
	"lmt": "2013-10-11T08:51:15+00:00",
	"lead_image": "http://www.washingtonpost.com/blogs/worldviews/files/2013/09/91114059.jpg",
	"items": []
}
```

#### Supported item types<a name="supported"></a>
* [image](#image)
* [sanitized_html](#html)
* [tweet](#tweet)
* [unsupported](#unsupported)
* [video](#video)

#### Image<a name="image"></a>

Example response
```
{
	"type": "image",
	"caption": "Diplomats meet at the United Nations Security Council (Hiroko Masuike/Getty Images)",
	"src": "http://www.washingtonpost.com/blogs/worldviews/files/2013/09/91114059.jpg"
},
```

#### Sanitized_html<a name="tweet"></a>

Example response
```
{
    "type": "sanitized_html",
    "content": "That's surely going to involve some tough negotiations in the council over what the resolution looks like. Now <a href=\"http://www.washingtonpost.com/world/middle_east/france-to-author-security-council-resolution-to-require-syria-to-give-up-chemical-weapons/2013/09/10/0d51a06c-19ff-11e3-a628-7e6dde8f889d_story.html\">we have the starting point for those negotiations</a>: a draft resolution submitted by France and backed by the United States and Britain."
}
```

#### Tweet<a name="tweet"></a>

Example response
```
{
    "type": "tweet",
	"id": "410086210095173632",
	"url": "https://twitter.com/angustweets/status/410086210095173632",
	"author": "angus croll",
	"content": "world's coldest person"
}
```

#### Unsupported<a name="unsupported"></a>

Content that doesn't translate from desktop to mobile apps such as JS and flash embeds.

List of unsupported content:
* Galleries // Need to be updated when gallery builder is finished
* Flash objects and embeds
* Script tags
* CSS links

Response
```
{
	"type": "unsupported",
	"content": "This embedded element is not supported in mobile applications. Sorry."
}
```

#### Video<a name="video"></a>

Supported video hosts
* posttv
* youtube
* vimeo

Example response
```
{
    "type": "video",
    "url": "www.youtube.com/embed/w6fRFCmrjvY",
    "host": "youtube",
    "id": "w6fRFCmrjvY",
    "thumbnail": "http://img.youtube.com/vi/w6fRFCmrjvY/0.jpg"
}
```

## Installation

This section describes how to install the plugin and get it working.

1. Upload `json-mobile` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

## Changelog

#### 0.1 ####
* Initial release