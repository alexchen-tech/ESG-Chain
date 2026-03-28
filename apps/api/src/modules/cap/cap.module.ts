import { Module } from '@nestjs/common'
import { CAPController } from './cap.controller'
import { CAPService } from './cap.service'

@Module({ controllers: [CAPController], providers: [CAPService] })
export class CAPModule {}
