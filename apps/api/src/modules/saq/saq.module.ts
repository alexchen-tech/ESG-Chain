import { Module } from '@nestjs/common'
import { SAQController } from './saq.controller'
import { SAQService } from './saq.service'

@Module({ controllers: [SAQController], providers: [SAQService] })
export class SAQModule {}
