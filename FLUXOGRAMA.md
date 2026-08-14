# Fluxograma resumido do sistema

```mermaid
flowchart TD
    A[Tela de login] --> B{Tipo de usuário}
    B -->|Convidado| C[Cria conta ou entra]
    B -->|Organizador| D[Painel interno]
    B -->|Super Admin| E[Painel administrativo]

    C --> F[Cadastra solicitação]
    F --> G[Escolhe data disponível]
    G --> H[Marca trajeto no mapa]
    H --> I[Solicitação enviada]
    I --> J{Análise da equipe}
    J -->|Aprovar| K[Aba Aprovadas]
    J -->|Negar| L[Aba Negadas]
    J -->|Pedir alteração| M[Convidado recebe retorno]
    M --> N[Edita e reenvia]
    N --> J

    D --> O[Cria corrida interna]
    O --> P[Revisa os dados]
    P --> Q[Confirma e publica]
    Q --> K

    E --> J
    E --> R[Gerencia usuários e poderes]
```

## Perfis

- **Administrador:** gerencia usuários e também avalia solicitações.
- **Organizador:** avalia solicitações e cadastra corridas internas com revisão antes da publicação.
- **Convidado:** envia e acompanha somente as próprias solicitações.

