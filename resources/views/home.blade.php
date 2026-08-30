@php
    $requestHost = strtolower(request()->getHost());
    $baseDomain = strtolower((string) config('domains.base_domain'));
    $isMainSite = in_array($requestHost, [$baseDomain, 'www.'.$baseDomain], true);
    $adminHref = $isMainSite ? '/acesso?tipo=equipe' : '/login';
    $clientHref = $isMainSite ? '/acesso?tipo=cliente' : '/portal/login';
    $canonicalUrl = $isMainSite ? config('domains.site_url').'/' : 'https://'.$requestHost.'/';
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Aluguel de motos com praticidade, suporte próximo e planos para a sua rotina. Conheça a LocX.">
    <meta name="theme-color" content="#090b0d">
    <meta property="og:title" content="LocX | Aluguel de Motos">
    <meta property="og:description" content="Aluguel de motos, compra e venda com atendimento próximo e processo simples.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <title>LocX | Aluguel de Motos</title>
    <link rel="stylesheet" href="{{ \App\Support\RentalSupport::asset('assets/css/home.css') }}">
</head>
<body>
<header class="site-header" data-site-header>
    <div class="nav-shell">
        <a class="home-logo" href="#inicio" aria-label="LocX — página inicial">
            <img src="{{ \App\Support\RentalSupport::asset('assets/img/logo-locx.jpg') }}" alt="LocX Aluguel de Motos" width="990" height="513">
        </a>

        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="homeMenu" data-menu-toggle>
            <span></span><span></span><span></span>
            <span class="sr-only">Abrir menu</span>
        </button>

        <nav class="home-menu" id="homeMenu" aria-label="Menu principal" data-menu>
            <a href="#inicio">Início</a>
            <a href="#servicos">Serviços</a>
            <a href="#vantagens">Vantagens</a>
            <a href="#loja">Nossa loja</a>
            <a href="#contato">Contato</a>
            <div class="menu-access">
                <a class="menu-client" href="{{ $clientHref }}">Portal do cliente</a>
                <a class="menu-admin" href="{{ $adminHref }}">Área administrativa <span aria-hidden="true">↗</span></a>
            </div>
        </nav>
    </div>
</header>

<main>
    <section class="hero" id="inicio">
        <div class="hero-glow hero-glow-one"></div>
        <div class="hero-glow hero-glow-two"></div>
        <div class="hero-inner">
            <div class="hero-copy">
                <span class="eyebrow"><i></i> Sua próxima moto começa aqui</span>
                <h1>Mais liberdade.<br><em>Menos complicação.</em></h1>
                <p>Alugue uma moto para trabalhar, ganhar tempo ou transformar sua rotina. Na LocX, você encontra atendimento próximo e uma solução que cabe nos seus planos.</p>
                <div class="hero-actions">
                    <a class="primary-cta" href="https://wa.me/5521992712611?text=Ol%C3%A1%2C%20vim%20pelo%20site%20da%20LocX%20e%20quero%20saber%20mais%20sobre%20o%20aluguel%20de%20motos." target="_blank" rel="noopener">
                        Quero alugar uma moto <span aria-hidden="true">↗</span>
                    </a>
                    <a class="text-cta" href="#servicos">Conheça nossos serviços <span aria-hidden="true">↓</span></a>
                </div>
                <div class="hero-trust" aria-label="Diferenciais LocX">
                    <span><b>✓</b> Processo simples</span>
                    <span><b>✓</b> Atendimento humano</span>
                    <span><b>✓</b> Suporte de verdade</span>
                </div>
            </div>

            <div class="hero-visual">
                <div class="photo-frame">
                    <img src="{{ \App\Support\RentalSupport::asset('assets/img/locx-storefront.jpeg') }}" alt="Fachada e interior da loja LocX Aluguel de Motos" width="960" height="1280">
                    <div class="photo-shade"></div>
                    <span class="photo-label"><i></i> Loja física e atendimento próximo</span>
                </div>
                <div class="hero-stamp" aria-hidden="true">
                    <span>LocX</span>
                    <small>VAI DE MOTO</small>
                </div>
            </div>
        </div>
        <a class="scroll-cue" href="#servicos" aria-label="Rolar para os serviços"><span></span></a>
    </section>

    <section class="service-section" id="servicos">
        <div class="section-shell">
            <div class="section-heading">
                <div>
                    <span class="section-kicker">O que fazemos</span>
                    <h2>Soluções sobre duas rodas<br>para cada momento.</h2>
                </div>
                <p>Do aluguel à negociação da sua moto, nossa equipe ajuda você a encontrar o caminho mais simples.</p>
            </div>

            <div class="service-grid">
                <article class="service-card featured">
                    <span class="card-number">01</span>
                    <div class="service-icon" aria-hidden="true">↗</div>
                    <h3>Aluguel de motos</h3>
                    <p>Planos práticos para quem quer mobilidade, autonomia e uma moto pronta para rodar.</p>
                    <a href="https://wa.me/5521992712611?text=Ol%C3%A1%2C%20quero%20conhecer%20os%20planos%20de%20aluguel%20da%20LocX." target="_blank" rel="noopener">Consultar planos <span>→</span></a>
                </article>
                <article class="service-card">
                    <span class="card-number">02</span>
                    <div class="service-icon" aria-hidden="true">◇</div>
                    <h3>Compra de motos</h3>
                    <p>Quer vender sua moto? Fale com a nossa equipe e receba uma avaliação clara e direta.</p>
                    <a href="#contato">Quero vender <span>→</span></a>
                </article>
                <article class="service-card">
                    <span class="card-number">03</span>
                    <div class="service-icon" aria-hidden="true">✦</div>
                    <h3>Venda de motos</h3>
                    <p>Encontre oportunidades selecionadas e conte com orientação em todo o processo.</p>
                    <a href="#contato">Ver oportunidades <span>→</span></a>
                </article>
            </div>
        </div>
    </section>

    <section class="benefits-section" id="vantagens">
        <div class="section-shell benefit-layout">
            <div class="benefit-intro">
                <span class="section-kicker light">Por que escolher a LocX</span>
                <h2>Uma parceria que acompanha o seu ritmo.</h2>
                <p>Mais do que entregar uma moto, queremos deixar sua jornada mais leve do primeiro contato em diante.</p>
                <a class="outline-cta" href="#contato">Falar com um especialista <span>→</span></a>
            </div>
            <div class="benefit-list">
                <article><span>01</span><div><h3>Atendimento próximo</h3><p>Você conversa com pessoas que entendem sua necessidade e ajudam a escolher com segurança.</p></div></article>
                <article><span>02</span><div><h3>Agilidade no processo</h3><p>Informação objetiva e menos burocracia para você começar a rodar quanto antes.</p></div></article>
                <article><span>03</span><div><h3>Solução completa</h3><p>Aluguel, compra e venda reunidos em um só lugar, com suporte em cada etapa.</p></div></article>
            </div>
        </div>
    </section>

    <section class="store-section" id="loja">
        <div class="section-shell store-layout">
            <div class="store-photo">
                <img src="{{ \App\Support\RentalSupport::asset('assets/img/locx-storefront.jpeg') }}" alt="Loja física LocX" width="960" height="1280" loading="lazy">
            </div>
            <div class="store-copy">
                <span class="section-kicker">Nossa loja</span>
                <h2>De portas abertas para a sua próxima conquista.</h2>
                <p>Venha conversar com a gente, conhecer as opções disponíveis e descobrir qual solução combina com o seu momento.</p>
                <div class="store-contact">
                    <span>ATENDIMENTO</span>
                    <a href="tel:+5521992712611">(21) 99271-2611</a>
                </div>
                <a class="dark-cta" href="https://wa.me/5521992712611" target="_blank" rel="noopener">Chamar no WhatsApp <span>↗</span></a>
            </div>
        </div>
    </section>

    <section class="contact-section" id="contato">
        <div class="contact-orb"></div>
        <div class="section-shell contact-inner">
            <span>PRONTO PARA COMEÇAR?</span>
            <h2>Sua próxima jornada<br>pode começar agora.</h2>
            <p>Conte para a nossa equipe o que você precisa. A gente ajuda a encontrar a melhor opção.</p>
            <a class="primary-cta large" href="https://wa.me/5521992712611?text=Ol%C3%A1%2C%20quero%20falar%20com%20a%20equipe%20LocX." target="_blank" rel="noopener">Conversar com a LocX <span>↗</span></a>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="footer-shell">
        <img src="{{ \App\Support\RentalSupport::asset('assets/img/logo-locx.jpg') }}" alt="LocX Aluguel de Motos" width="990" height="513">
        <p>Aluguel de motos · Compra e venda</p>
        <nav aria-label="Links do rodapé">
            <a href="#servicos">Serviços</a>
            <a href="#loja">Nossa loja</a>
            <a href="{{ $clientHref }}">Portal do cliente</a>
            <a href="{{ $adminHref }}">Área administrativa</a>
        </nav>
        <small>© {{ date('Y') }} LocX. Todos os direitos reservados.</small>
    </div>
</footer>

<a class="whatsapp-float" href="https://wa.me/5521992712611?text=Ol%C3%A1%2C%20vim%20pelo%20site%20da%20LocX." target="_blank" rel="noopener" aria-label="Falar com a LocX no WhatsApp">
    <svg viewBox="0 0 32 32" aria-hidden="true"><path fill="currentColor" d="M16.04 3a12.73 12.73 0 0 0-10.9 19.31L3 29l6.9-2.05A12.75 12.75 0 1 0 16.04 3Zm0 2.15a10.6 10.6 0 1 1-5.42 19.72l-.38-.23-4.1 1.22 1.27-3.98-.25-.4a10.58 10.58 0 0 1 8.88-16.33Zm-4.55 4.56c-.2 0-.52.08-.79.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.22 3.08c.15.2 2.1 3.2 5.08 4.49.7.3 1.26.49 1.7.62.7.23 1.35.2 1.86.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.3.18-1.42-.08-.12-.28-.2-.58-.35-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.18.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48a9.1 9.1 0 0 1-1.66-2.07c-.17-.3-.02-.46.13-.61.14-.13.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51h-.57Z"/></svg>
</a>

<script src="{{ \App\Support\RentalSupport::asset('assets/js/home.js') }}" defer></script>
</body>
</html>
