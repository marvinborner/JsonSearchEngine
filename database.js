const elasticsearch = require('elasticsearch');
const esClient = new elasticsearch.Client({
    host: '127.0.0.1:9200',
    log: 'error'
});

module.exports = {
    index: function bulkIndex(index, type, data) {
        let bulkBody = [];

        data.forEach(item => {
            bulkBody.push({
                index: {
                    _index: index,
                    _type: type,
                    _id: item.id
                }
            });

            bulkBody.push(item);
        });

        esClient.bulk({body: bulkBody})
            .then(response => {
                let errorCount = 0;
                response.items.forEach(item => {
                    if (item.index && item.index.error) {
                        console.log(++errorCount, item.index.error);
                    }
                });
                console.log(`Successfully indexed item`);
            })
            .catch(console.err);
    },
    search: function search(index, body) {
        return esClient.search({index: index, body: body});
    }
};