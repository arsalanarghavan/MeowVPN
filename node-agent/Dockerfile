FROM golang:1.22-alpine AS build
WORKDIR /src
COPY go.mod ./
COPY cmd ./cmd
COPY internal ./internal
RUN go build -o /meow-node-agent ./cmd/agent

FROM alpine:3.20
RUN apk add --no-cache ca-certificates curl \
    && curl -L -o /tmp/xray.zip https://github.com/XTLS/Xray-core/releases/latest/download/Xray-linux-64.zip \
    && apk add --no-cache unzip \
    && unzip -o /tmp/xray.zip -d /usr/local/bin \
    && chmod +x /usr/local/bin/xray \
    && rm -rf /tmp/xray.zip \
    && apk del unzip curl

COPY --from=build /meow-node-agent /usr/local/bin/meow-node-agent
VOLUME ["/etc/xray"]
EXPOSE 8444
ENTRYPOINT ["/usr/local/bin/meow-node-agent"]
CMD ["-addr", ":8444", "-config", "/etc/xray/config.json"]
