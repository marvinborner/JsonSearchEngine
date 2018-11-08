const crawlService = require("crawler");
const crypto = require("crypto");
const database = require("./database");
const url = require("url");

const crawler = new crawlService({
    userAgent: "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)",
    rateLimit: 100,
    maxConnections: 1, // set to 10 (and remove the line above) for faster crawling but higher probability of rate limiting (429)
    callback: (error, res, done) => {
        if (error || res.statusCode !== 200) {
            console.log("Error: " + error);
            console.log("Code: " + res.statusCode);
        } else {
            const $ = res.$;
            const urlHash = crypto.createHash("sha256").update(res.request.uri.href).digest("base64");
            if (database.exists("crawled", "site", urlHash)) {
                console.log("\nCrawling: " + res.request.uri.href);
                database.index('crawled', 'site', [
                    {
                        "id": urlHash,
                        "url": res.request.uri.href,
                        "title": $("title").text() || res.request.uri.href,
                        "description": $("meta[name=description]").attr("content") || "",
                        "keywords": $("meta[name=keywords]").attr("content") ? $("meta[name=keywords]").attr("content").split(", ") : ""
                    }
                ]);

                $("a").map((i, tag) => {
                    let parsed;
                    try {
                        parsed = new URL($(tag).attr("href"));
                    } catch (e) { // invalid url -> probably a path
                        parsed = new URL($(tag).attr("href"), res.request.uri.href);
                    }
                    if (parsed.origin !== "null") {
                        console.log("Queueing: " + parsed.origin + parsed.pathname);
                        crawler.queue(parsed.origin + parsed.pathname);
                    }
                });
            }
        }
        done();
    }
});

crawler.queue('http://wikipedia.com');
