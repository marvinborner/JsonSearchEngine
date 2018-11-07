const crawlService = require("crawler");
const crypto = require("crypto");
const database = require("./database");

const crawler = new crawlService({
    maxConnections: 10,
    callback: (error, res, done) => {
        if (error) {
            console.log(error);
        } else {
            const $ = res.$;
            database.index('crawled', 'site', [
                {
                    "id": crypto.createHash('sha256').update(res.request.uri.href).digest('base64'),
                    "url": res.request.uri.href,
                    "title": $("title").text(),
                    "description": $("meta[name=description]").attr("content"),
                    "keywords": $("meta[name=keywords]").attr("content").split(", ")
                }
            ]);
        }
        done();
    }
});

crawler.queue('http://www.amazon.com');
