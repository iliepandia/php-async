import http from "http";
import {URL} from "url";

const hostname = '127.0.0.1';
const port = 9010;


const server = http.createServer(async (req, res) => {

    const url = new URL( req.url, `http://${req.headers.host}`);
    const queryParams = url.searchParams;

    const delayBetweenChunks = parseInt(queryParams.get('delay')||500);
    const chunkNumbers = parseInt(queryParams.get('chunks')||100);
    const fileLines = parseInt(queryParams.get('size')||1_000);

    console.log( `Responding to request. Delay: ${delayBetweenChunks} Chunks:${chunkNumbers} Lines:${fileLines}`);

    const responseBody = "Start!\n" + Array(fileLines).fill("Hello world. 🌍\n").join("")  + "\nThe End\n"; // Total response content
    const responseBuffer = Buffer.from( responseBody, 'utf-8');
    const responseLength = responseBuffer.length; // Calculate the length of the response
    const chunkSize = Math.ceil(responseLength / chunkNumbers); // Split the response into 10 chunks

    res.statusCode = 200;
    res.setHeader('Content-Type', 'text/plain');
    res.setHeader("Content-Length", responseLength);

    for(let offset=0; offset<responseBuffer.length; offset += chunkSize ){
        const chunk = responseBuffer.slice(offset,offset+chunkSize);
        res.write(chunk)
        await new Promise((resolve)=>setTimeout(resolve,delayBetweenChunks));
    }

    res.end();
});

server.listen(port, hostname, () => {
    console.log(`Server running at http://${hostname}:${port}/`);
});
