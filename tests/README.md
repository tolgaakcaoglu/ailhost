# Tests

Bu dizin panel, backend ve worker katmani testlerini tutar.

## Calistirma

```bash
php tests/run.php
```

## Mevcut Kapsam

- PHP syntax kontrolu
- Router smoke test
- Job service lifecycle testi
- Dashboard view smoke testi

Testler dis bagimlilik kullanmaz ve gecici dizinde calisir. Repo `var/` state dosyalari test tarafindan degistirilmemelidir.
