In `app/config/config.yml` add the platform for your connection name: 

```yaml
propel:
    generator:
        defaultConnection: default
        connections:       [ default ]
        platformClass:     \APinnecke\CompositeNumberRange\CompositeNumberRangeMysqlPlatform
```