# BaseStream TV — Tizen Package

## Estrutura do pacote

```
tizen/
├── config.xml   ← Manifest do app Tizen
├── index.html   ← Loader (redireciona para o servidor Railway)
├── icon.png     ← Ícone 512×512 (adicione manualmente)
└── README.md    ← Este arquivo
```

## Como gerar o .wgt

### Pré-requisitos
- [Tizen Studio](https://developer.tizen.org/development/tizen-studio/download) instalado
- Certificate Profile configurado (Samsung Developers)

### Passos via Tizen Studio

1. Abra o Tizen Studio
2. File → Import → Tizen → Tizen Project
3. Selecione esta pasta `tizen/`
4. Clique com o botão direito no projeto → Build Signed Package
5. O arquivo `.wgt` será gerado em `tizen/.buildResult/`

### Passos via CLI (alternativo)

```bash
# No diretório tizen/
tizen package -t wgt -s MyCertProfile -- .
```

### Instalação na TV

**Método 1 – TV Developer Mode**
1. Na Samsung Smart TV: Configurações → Geral → Modo Desenvolvedor → Ativar
2. Defina o IP do seu PC
3. Tizen Studio → Device Manager → Remote Device → Add → IP da TV
4. Clique direito no projeto → Run As → Tizen Web Application

**Método 2 – USB**
1. Copie o `.wgt` para um pen drive
2. Na TV: Samsung Apps → navegue até o arquivo → instale

**Método 3 – Deploy remoto (CI/CD)**
```bash
tizen install -n BaseStreamTV.wgt -t <device-serial>
```

## Como funciona

Este é um **Hosted Web App** — o `.wgt` apenas empacota um loader que redireciona para:

```
https://basestream-production.up.railway.app/tv
```

Toda a lógica roda no servidor. A TV precisa de conexão com a internet.

## Adicionando o ícone

Crie ou exporte um arquivo `icon.png` (512×512 px) e salve em `tizen/icon.png`.

Ferramentas sugeridas:
- [Samsung TV App Icon Template](https://developer.samsung.com/smarttv/develop/distribute/deploy-app.html)
- Canva / Figma exportando 512×512

## Requisitos da TV

- Samsung Smart TV com Tizen 2.3+
- Conexão WiFi ou Ethernet
- Credenciais Xtream geradas em: `https://basestream-production.up.railway.app/dashboard/iptv`
