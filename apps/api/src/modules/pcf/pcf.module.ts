import { Module } from '@nestjs/common'
import { PCFController } from './pcf.controller'
import { PCFService } from './pcf.service'

@Module({ controllers: [PCFController], providers: [PCFService] })
export class PCFModule {}
