package main

import (
	"flag"
	"log"
	"os"

	"github.com/meowvpn/node-agent/internal/api"
)

func main() {
	addr := flag.String("addr", ":8444", "listen address")
	configPath := flag.String("config", "/etc/xray/config.json", "xray config path")
	xrayBin := flag.String("xray", "/usr/local/bin/xray", "xray binary path")
	certFile := flag.String("cert", "", "TLS cert file (optional)")
	keyFile := flag.String("key", "", "TLS key file (optional)")
	flag.Parse()

	srv := api.NewServer(*configPath, *xrayBin)
	log.Printf("meow-node-agent listening on %s", *addr)
	if err := srv.ListenAndServe(*addr, *certFile, *keyFile); err != nil {
		log.Println(err)
		os.Exit(1)
	}
}
