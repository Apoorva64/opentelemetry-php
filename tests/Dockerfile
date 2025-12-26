FROM alpine:latest

RUN apk add --no-cache curl bash jq

WORKDIR /tests

COPY integration-test.sh .
RUN chmod +x integration-test.sh

CMD ["./integration-test.sh"]
