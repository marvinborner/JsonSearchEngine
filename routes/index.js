const database = require("../database");
const express = require("express");
const router = express.Router();

/* GET home page. */
router.get('/', (req, res, next) => {
    database.search("crawled", {
        size: 20,
        from: 0,
        query: {
            match_all: {}
        }
    }).then(results => {
        res.json(results.hits)
    })
});

module.exports = router;