# deployer-action by qlicks



## Deployer action for build deploy and create stage for magento 2.x

### Features:

- Build project 

- Integrated custom enviroment variables

- Integrated composer cache

- Deploy production or stage

- Notifaction to slack with step status

- Auto backups databases

  
  
  

  #### Build project and generate composer diff example github action configuration:

  ```yaml
  name: CI
  
  on:
    pull_request:
        branches: [master, STAGE]
        
    workflow_dispatch:
  
  jobs:
    composer-diff:
      name: Composer Diff
      runs-on: self-hosted
      steps:
        - id: Checkout
          uses: actions/checkout@v2
          with:
            fetch-depth: 0
        - name: Deployer Get composer.lock from server
          uses: qlicks/deployer-action@master
          env: 
              SSH_PRIVATE_KEY: ${{ secrets.SSH_KEY }}
          with:
              task: composer:lock:get 
              target: master
              extra_arguments: -vvvv
                    
        - name: Build
          uses: qlicks/deployer-action@master
          env: 
              COMPOSER_AUTH: ${{ secrets.AUTH }}
          with:
              task: build
              target: build
              extra_arguments: -vvvv
              
        - name: Generate composer diff
          id: composer_diff 
          uses: qlicks/composer-diff-action@master
          env: 
              GITHUB_TOKEN: ${{ secrets.TOKEN }}
          with:
              base: "./old_composer.lock"
              target: "./composer.lock"
              with-links: true
              
        - uses: marocchino/sticky-pull-request-comment@v2
          with:
            header: composer-diff
            path: composer.diff
  ```

  #### Build and deploy magento to server example configuration:

```yaml
name: CD

on:
  workflow_dispatch:
    inputs:
      stage:
         description: 'Deploy to production or STAGE'
         required: true
         default: 'STAGE'

env:
  COMPOSER_AUTH: ${{ secrets.AUTH }}
  SLACK_WEBHOOK_URL:  ${{ secrets.SLACK_WEBHOOK }}
  SSH_PRIVATE_KEY: ${{ secrets.SSH_KEY }}

jobs:
  build:
    runs-on: self-hosted
    steps:
      - id: Checkout
        uses: actions/checkout@v2
        with:
          fetch-depth: 0
      - name: Build
        uses: qlicks/deployer-action@v1
        with:
            task: build
            target: build
            slack_channel: deploy-alerts

      - uses: actions/upload-artifact@v2
        with:
            name: artifacts
            path: artifacts/**
      - uses: 8398a7/action-slack@v3
        with:
          status: ${{ job.status }}
          channel: '#deploy-alerts'
          fields: repo,message,commit,author,action,eventName,ref 
        if: failure() 
            
  deploy:
    runs-on: self-hosted
    needs: build
    steps:
      - id: Checkout
        uses: actions/checkout@v2
        with:
          fetch-depth: 0
      - uses: actions/download-artifact@v2
        with:
            name: artifacts
            path: artifacts/
            
      - name: Deploy
        uses: qlicks/deployer-action@v1
        with:
            task: deploy-artifact
            target: ${{ github.event.inputs.stage }}
            slack_channel: deploy-alerts
            
      - uses: 8398a7/action-slack@v3
        with:
          status: ${{ job.status }}
          channel: '#deploy-alerts'
          fields: repo,message,commit,author,action,eventName,ref 
        if: always() 
  ```

  
